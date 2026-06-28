<?php

use App\Actions\Payouts\CreatePayoutExceptionCase;
use App\Actions\Payouts\DispatchPayoutTransfer;
use App\Actions\Payouts\ProcessPayout;
use App\Actions\Payouts\ReconcilePayoutTransfer;
use App\Actions\Payouts\ReleaseFailedPayoutBalance;
use App\Actions\Payouts\VerifyPayoutAccount;
use App\Contracts\Payouts\PayoutProvider;
use App\Enums\PaymentProviderName;
use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutBatchStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\ProviderWebhookEventStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\PayoutAttempt;
use App\Models\PayoutBatch;
use App\Models\ProviderWebhookEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Support\Payouts\BankAccountResolution;
use App\Support\Payouts\PayoutProviderException;
use App\Support\Payouts\TransferDispatch;
use App\Support\Payouts\TransferRecipient;
use App\Support\Payouts\TransferVerification;
use App\Support\Payouts\UncertainPayoutException;
use Carbon\CarbonInterface;
use Database\Seeders\PlatformAccessSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PlatformAccessSeeder::class);
    config()->set('services.paystack.secret_key', 'test-secret');
    config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    config()->set('lartisan.payouts.max_attempts', 3);
    config()->set('lartisan.payouts.retry_delay_minutes', 10);
    config()->set('lartisan.payouts.stale_processing_minutes', 1);
});

test('scheduled batches verify payout accounts and dispatch provider transfers', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'message' => 'Account resolved',
            'data' => [
                'account_name' => 'Resolved Artisan',
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'Guaranty Trust Bank',
            ],
        ]),
        'https://api.paystack.co/transferrecipient' => Http::response([
            'status' => true,
            'message' => 'Recipient created',
            'data' => [
                'recipient_code' => 'RCP_phase14',
                'status' => 'active',
            ],
        ]),
        'https://api.paystack.co/transfer' => fn (Request $request) => Http::response([
            'status' => true,
            'message' => 'Transfer queued',
            'data' => [
                'reference' => $request['reference'],
                'status' => 'pending',
                'transfer_code' => 'TRF_phase14',
            ],
        ]),
    ]);
    $context = phaseFourteenApprovedPayout(verifiedAccount: false);

    expect(Artisan::call('payouts:dispatch-approved'))->toBe(0)
        ->and(Artisan::output())->toContain('Processed payout batch');

    $payout = $context['payout']->refresh();
    $account = $context['account']->refresh();
    $attempt = $payout->attempts()->firstOrFail();
    $batch = $payout->batch()->firstOrFail();

    expect($account->status)->toBe(PayoutAccountStatus::Verified)
        ->and($account->account_name)->toBe('Resolved Artisan')
        ->and($account->recipient_code)->toBe('RCP_phase14')
        ->and($payout->status)->toBe(PayoutStatus::Processing)
        ->and($payout->provider_reference)->toBe('payout-'.$payout->id.'-attempt-1')
        ->and($payout->provider_transfer_code)->toBe('TRF_phase14')
        ->and($batch)->toBeInstanceOf(PayoutBatch::class)
        ->and($batch->status)->toBe(PayoutBatchStatus::Completed)
        ->and($attempt->status)->toBe(PayoutAttemptStatus::Processing)
        ->and($attempt->provider_transfer_code)->toBe('TRF_phase14');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.paystack.co/bank/resolve'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.paystack.co/transferrecipient');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.paystack.co/transfer'
        && $request['source'] === 'balance');
});

test('paystack success webhooks mark payouts paid idempotently without double debiting', function () {
    $context = phaseFourteenDispatchedPayout();
    $payout = $context['payout']->refresh();
    $payload = phaseFourteenTransferWebhookPayload('transfer.success', $payout, 'success');

    postJson(route('webhooks.paystack'), $payload, [
        'x-paystack-signature' => phaseFourteenSignature($payload),
    ])->assertOk();
    postJson(route('webhooks.paystack'), $payload, [
        'x-paystack-signature' => phaseFourteenSignature($payload),
    ])->assertOk();

    $payout = $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->paid_at)->not->toBeNull()
        ->and($payout->ledgerEntries()->where('type', WalletLedgerEntryType::PayoutDebit)->count())->toBe(1)
        ->and($payout->ledgerEntries()->where('type', WalletLedgerEntryType::AdjustmentCredit)->count())->toBe(0)
        ->and($payout->attempts()->count())->toBe(1)
        ->and(ProviderWebhookEvent::query()->where('event', 'transfer.success')->count())->toBe(1);
});

test('terminal transfer failures release reserved balances once and open finance cases', function () {
    config()->set('lartisan.payouts.max_attempts', 1);
    $context = phaseFourteenDispatchedPayout();
    $payout = $context['payout']->refresh();
    $payload = phaseFourteenTransferWebhookPayload('transfer.failed', $payout, 'failed', 'Bank rejected transfer.');

    postJson(route('webhooks.paystack'), $payload, [
        'x-paystack-signature' => phaseFourteenSignature($payload),
    ])->assertOk();
    postJson(route('webhooks.paystack'), $payload, [
        'x-paystack-signature' => phaseFourteenSignature($payload),
    ])->assertOk();

    $payout = $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed)
        ->and($payout->wallet()->firstOrFail()->available_balance)->toBe(500000)
        ->and($payout->ledgerEntries()->where('immutable_reference', 'payout-'.$payout->id.'-failed-release')->count())->toBe(1)
        ->and($payout->supportCases()->where('category', SupportCaseCategory::Payout)->count())->toBe(1);
});

test('reversed paid transfers become adjusted and credit the artisan wallet once', function () {
    $context = phaseFourteenDispatchedPayout();
    $payout = $context['payout']->refresh();
    $successPayload = phaseFourteenTransferWebhookPayload('transfer.success', $payout, 'success');
    $reversalPayload = phaseFourteenTransferWebhookPayload('transfer.reversed', $payout, 'reversed', 'Provider reversed transfer.');

    postJson(route('webhooks.paystack'), $successPayload, [
        'x-paystack-signature' => phaseFourteenSignature($successPayload),
    ])->assertOk();
    postJson(route('webhooks.paystack'), $reversalPayload, [
        'x-paystack-signature' => phaseFourteenSignature($reversalPayload),
    ])->assertOk();
    postJson(route('webhooks.paystack'), $reversalPayload, [
        'x-paystack-signature' => phaseFourteenSignature($reversalPayload),
    ])->assertOk();

    $payout = $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Adjusted)
        ->and($payout->wallet()->firstOrFail()->available_balance)->toBe(500000)
        ->and($payout->ledgerEntries()->where('immutable_reference', 'payout-'.$payout->id.'-reversal-credit')->count())->toBe(1)
        ->and($payout->supportCases()->where('subject', 'Payout transfer reversed')->count())->toBe(1);
});

test('otp required transfer responses move payouts to finance review without retrying automatically', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/transfer' => fn (Request $request) => Http::response([
            'status' => false,
            'message' => 'Transfer requires OTP to continue.',
            'data' => [
                'reference' => $request['reference'],
                'status' => 'otp',
            ],
        ], 400),
    ]);
    $context = phaseFourteenApprovedPayout();

    app(DispatchPayoutTransfer::class)->handle($context['payout']);

    $payout = $context['payout']->refresh();
    $attempt = $payout->attempts()->firstOrFail();

    expect($payout->status)->toBe(PayoutStatus::InReview)
        ->and($payout->provider_status)->toBe('action_required')
        ->and($attempt->status)->toBe(PayoutAttemptStatus::ActionRequired)
        ->and($payout->supportCases()->where('subject', 'Payout transfer requires finance action')->count())->toBe(1)
        ->and($payout->ledgerEntries()->where('type', WalletLedgerEntryType::AdjustmentCredit)->count())->toBe(0);
});

test('polling reconciliation resolves uncertain processing transfers', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/transfer' => Http::failedConnection(),
    ]);
    $context = phaseFourteenApprovedPayout();

    app(DispatchPayoutTransfer::class)->handle($context['payout']);
    $context['payout']->refresh()->forceFill(['processing_at' => now()->subMinutes(10)])->save();
    Http::fake([
        'https://api.paystack.co/transfer/verify/*' => Http::response([
            'status' => true,
            'message' => 'Transfer verified',
            'data' => [
                'amount' => 100000,
                'currency' => 'NGN',
                'reference' => $context['payout']->refresh()->provider_reference,
                'status' => 'success',
                'transfer_code' => 'TRF_polled',
            ],
        ]),
    ]);

    expect(Artisan::call('payouts:reconcile-processing'))->toBe(0)
        ->and(Artisan::output())->toContain('Reconciled 1 processing payout');

    expect($context['payout']->refresh()->status)->toBe(PayoutStatus::Paid)
        ->and($context['payout']->refresh()->provider_transfer_code)->toBe('TRF_polled');
});

test('artisan wallet payout props expose safer status details', function () {
    $context = phaseFourteenDispatchedPayout();
    $payout = $context['payout']->refresh();

    $this->actingAs($context['owner'])
        ->get(route('artisan.wallet.show', ['current_team' => $context['team']->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Wallet')
            ->where('payouts.0.id', $payout->id)
            ->where('payouts.0.providerStatus', 'pending')
            ->where('payouts.0.trackingReference', 'TRF_dispatched')
            ->has('payouts.0.processingAt')
            ->missing('payoutAccounts.0.recipientCode'));
});

test('paystack payout provider handles provider failures fallbacks and signatures', function () {
    Http::preventStrayRequests();
    $provider = app(PayoutProvider::class);
    $context = phaseFourteenApprovedPayout();
    $account = $context['account'];
    $payout = $context['payout'];

    Http::fake([
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => ['unexpected' => true],
            'message' => ['not' => 'scalar'],
            'data' => 'not-array-data',
        ], 422),
    ]);

    $resolution = $provider->resolveBankAccount($account);

    expect($resolution->successful)->toBeFalse()
        ->and($resolution->providerStatus)->toBe('failed')
        ->and($resolution->failureReason)->toBe('Paystack could not resolve this account.');

    Http::fake([
        'https://api.paystack.co/bank/resolve*' => Http::failedConnection(),
    ]);

    expect(fn () => $provider->resolveBankAccount($account))
        ->toThrow(PayoutProviderException::class, 'Paystack could not verify the bank account.');

    Http::fake([
        'https://api.paystack.co/transferrecipient' => Http::failedConnection(),
    ]);

    expect(fn () => $provider->createTransferRecipient($account))
        ->toThrow(PayoutProviderException::class, 'Paystack could not create a transfer recipient.');

    config()->set('services.paystack.payment_url', 'https://paystack-recipient.test');
    Http::fake([
        'https://paystack-recipient.test/transferrecipient' => Http::response([
            'status' => true,
            'message' => 'Recipient code missing.',
            'data' => ['recipient_code' => '   '],
        ]),
    ]);

    expect(fn () => $provider->createTransferRecipient($account))
        ->toThrow(PayoutProviderException::class, 'Recipient code missing.');

    $attempt = PayoutAttempt::factory()->make([
        'attempt_number' => 7,
        'payout_id' => $payout->id,
        'provider_reference' => null,
    ]);
    $expectedReference = 'payout-'.$payout->id.'-attempt-7';

    config()->set('services.paystack.payment_url', 'https://paystack-transfer-success.test');
    Http::fake([
        'https://paystack-transfer-success.test/transfer' => fn (Request $request) => Http::response([
            'status' => true,
            'message' => 'Transfer queued',
            'data' => [
                'status' => 'pending',
                'transfer_code' => null,
            ],
        ]),
    ]);

    $dispatch = $provider->initiateTransfer($payout, $attempt);

    expect($dispatch->reference)->toBe($expectedReference)
        ->and($dispatch->providerStatus)->toBe('pending')
        ->and($dispatch->transferCode)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://paystack-transfer-success.test/transfer'
        && $request['reference'] === $expectedReference);

    config()->set('services.paystack.payment_url', 'https://paystack-transfer-runtime.test');
    Http::fake([
        'https://paystack-transfer-runtime.test/transfer' => fn () => throw new RuntimeException('Transport exploded.'),
    ]);

    expect(fn () => $provider->initiateTransfer($payout, PayoutAttempt::factory()->make([
        'attempt_number' => 8,
        'payout_id' => $payout->id,
        'provider_reference' => 'runtime-ref',
    ])))->toThrow(UncertainPayoutException::class, 'before a conclusive response.');

    config()->set('services.paystack.payment_url', 'https://paystack-transfer-failed.test');
    Http::fake([
        'https://paystack-transfer-failed.test/transfer' => Http::response([
            'status' => false,
            'message' => 'Balance low.',
            'data' => ['status' => 'failed'],
        ], 400),
    ]);

    expect(fn () => $provider->initiateTransfer($payout, PayoutAttempt::factory()->make([
        'attempt_number' => 9,
        'payout_id' => $payout->id,
        'provider_reference' => 'failed-ref',
    ])))->toThrow(PayoutProviderException::class, 'Balance low.');

    config()->set('services.paystack.payment_url', 'https://paystack-transfer-otp.test');
    Http::fake([
        'https://paystack-transfer-otp.test/transfer' => Http::response([
            'status' => true,
            'message' => 'Transfer queued',
            'data' => [
                'reference' => 'otp-data-ref',
                'status' => 'otp_required',
                'transfer_code' => 'TRF_otp',
            ],
        ]),
    ]);

    $actionRequiredDispatch = $provider->initiateTransfer($payout, PayoutAttempt::factory()->make([
        'attempt_number' => 10,
        'payout_id' => $payout->id,
        'provider_reference' => 'otp-data-ref',
    ]));

    expect($actionRequiredDispatch->actionRequired)->toBeTrue();

    config()->set('services.paystack.payment_url', 'https://paystack-verify-invalid.test');
    Http::fake([
        'https://paystack-verify-invalid.test/transfer/verify/*' => Http::response('not-json', 200),
    ]);

    expect(fn () => $provider->verifyTransfer('invalid-json-ref'))
        ->toThrow(PayoutProviderException::class, 'Paystack could not verify the transfer.');

    config()->set('services.paystack.payment_url', 'https://paystack-verify-connection.test');
    Http::fake([
        'https://paystack-verify-connection.test/transfer/verify/*' => Http::failedConnection(),
    ]);

    expect(fn () => $provider->verifyTransfer('connection-ref'))
        ->toThrow(PayoutProviderException::class, 'Paystack could not verify the transfer.');

    config()->set('services.paystack.payment_url', 'https://paystack-verify-success.test');
    Http::fake([
        'https://paystack-verify-success.test/transfer/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'failed',
                'reason' => 'Destination account closed.',
            ],
        ]),
    ]);

    $verification = $provider->verifyTransfer('reason-ref');

    expect($verification->reference)->toBe('reason-ref')
        ->and($verification->failureReason)->toBe('Destination account closed.')
        ->and($verification->isFailed())->toBeTrue();

    $payload = '{"event":"transfer.success"}';
    $signature = hash_hmac('sha512', $payload, 'test-secret');

    expect($provider->webhookSignatureIsValid($payload, $signature))->toBeTrue()
        ->and($provider->webhookSignatureIsValid($payload, null))->toBeFalse();

    config()->set('services.paystack.secret_key', null);

    expect($provider->webhookSignatureIsValid($payload, $signature))->toBeFalse();
});

test('payout batches expose finance relationships and typed counters', function () {
    $creator = phaseFourteenFinanceUser();
    $batch = PayoutBatch::factory()->processing()->create([
        'action_required_payouts' => '1',
        'completed_at' => now(),
        'created_by' => $creator->id,
        'failed_payouts' => '1',
        'metadata' => ['source' => 'coverage'],
        'successful_payouts' => '2',
        'total_amount' => '300000',
        'total_payouts' => '4',
    ]);
    $context = phaseFourteenApprovedPayout();
    $payout = $context['payout'];
    $payout->forceFill(['payout_batch_id' => $batch->id])->save();

    $batch = $batch->refresh();

    $batchCreator = $batch->createdBy()->firstOrFail();

    expect($batchCreator->is($creator))->toBeTrue()
        ->and($batch->payouts()->whereKey($payout->id)->exists())->toBeTrue()
        ->and($batch->status)->toBe(PayoutBatchStatus::Processing)
        ->and($batch->scheduled_for)->toBeInstanceOf(CarbonInterface::class)
        ->and($batch->started_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($batch->completed_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($batch->metadata)->toBe(['source' => 'coverage'])
        ->and($batch->action_required_payouts)->toBe(1)
        ->and($batch->failed_payouts)->toBe(1)
        ->and($batch->successful_payouts)->toBe(2)
        ->and($batch->total_amount)->toBe(300000)
        ->and($batch->total_payouts)->toBe(4);
});

test('payout account verification records rejections exceptions and verified noops', function () {
    $verifier = app(VerifyPayoutAccount::class);
    $verified = PayoutAccount::factory()->verified()->create();

    expect($verifier->handle($verified)->is($verified))->toBeTrue();

    config()->set('services.paystack.payment_url', 'https://verify-rejected.test');
    Http::preventStrayRequests();
    Http::fake([
        'https://verify-rejected.test/bank/resolve*' => Http::response([
            'status' => false,
            'message' => 'Account could not be resolved.',
        ], 422),
    ]);

    $rejected = $verifier->handle(PayoutAccount::factory()->create());

    expect($rejected->status)->toBe(PayoutAccountStatus::Rejected)
        ->and($rejected->verification_failure_reason)->toBe('Account could not be resolved.')
        ->and(data_get($rejected->metadata, 'bank_resolution.message'))->toBe('Account could not be resolved.');

    config()->set('services.paystack.payment_url', 'https://verify-resolution-exception.test');
    Http::fake([
        'https://verify-resolution-exception.test/bank/resolve*' => Http::failedConnection(),
    ]);

    $providerError = $verifier->handle(PayoutAccount::factory()->create());

    expect($providerError->status)->toBe(PayoutAccountStatus::Pending)
        ->and($providerError->verification_provider_status)->toBe('provider_error')
        ->and($providerError->verification_failure_reason)->toBe('Paystack could not verify the bank account.');

    config()->set('services.paystack.payment_url', 'https://verify-recipient-exception.test');
    Http::fake([
        'https://verify-recipient-exception.test/bank/resolve*' => Http::response([
            'status' => true,
            'data' => [
                'account_name' => 'Resolved Artisan',
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'Guaranty Trust Bank',
            ],
        ]),
        'https://verify-recipient-exception.test/transferrecipient' => Http::failedConnection(),
    ]);

    $recipientError = $verifier->handle(PayoutAccount::factory()->create([
        'account_number' => '0123456789',
    ]));

    expect($recipientError->account_name)->toBe('Resolved Artisan')
        ->and($recipientError->verification_provider_status)->toBe('provider_error')
        ->and($recipientError->verification_failure_reason)->toBe('Paystack could not create a transfer recipient.');
});

test('dispatching payout transfers handles review retry terminal and provider action branches', function () {
    $invalidContext = phaseFourteenApprovedPayout();
    $invalidContext['payout']->forceFill(['status' => PayoutStatus::Paid])->save();

    expect(fn () => app(DispatchPayoutTransfer::class)->handle($invalidContext['payout']->refresh()))
        ->toThrow(InvalidArgumentException::class, 'Only approved or retrying payouts can be dispatched.');

    $missingDebitContext = phaseFourteenApprovedPayout();
    $missingDebitContext['payout']->ledgerEntries()->delete();
    $missingDebit = app(DispatchPayoutTransfer::class)->handle($missingDebitContext['payout']);

    expect($missingDebit->status)->toBe(PayoutStatus::InReview)
        ->and($missingDebit->provider_status)->toBe('missing_debit')
        ->and($missingDebit->supportCases()->where('subject', 'Payout transfer requires finance action')->count())->toBe(1);

    config()->set('services.paystack.payment_url', 'https://dispatch-verification-rejected.test');
    Http::preventStrayRequests();
    Http::fake([
        'https://dispatch-verification-rejected.test/bank/resolve*' => Http::response([
            'status' => false,
            'message' => 'Account rejected by provider.',
        ], 422),
    ]);
    $batch = PayoutBatch::factory()->create();
    $unverifiedContext = phaseFourteenApprovedPayout(verifiedAccount: false);
    $unverified = app(DispatchPayoutTransfer::class)->handle($unverifiedContext['payout'], batch: $batch);

    expect($unverified->status)->toBe(PayoutStatus::InReview)
        ->and($unverified->payout_batch_id)->toBe($batch->id)
        ->and($unverified->payoutAccount()->firstOrFail()->status)->toBe(PayoutAccountStatus::Rejected);

    config()->set('lartisan.payouts.retry_delay_minutes', 'bad-config');
    config()->set('services.paystack.payment_url', 'https://dispatch-nonterminal-failure.test');
    Http::fake([
        'https://dispatch-nonterminal-failure.test/transfer' => Http::response([
            'status' => false,
            'message' => 'Provider declined transfer.',
            'data' => ['status' => 'failed'],
        ], 400),
    ]);
    $retryingContext = phaseFourteenApprovedPayout();
    $retrying = app(DispatchPayoutTransfer::class)->handle($retryingContext['payout']);

    expect($retrying->status)->toBe(PayoutStatus::Retrying)
        ->and($retrying->next_retry_at)->not->toBeNull()
        ->and($retrying->attempts()->firstOrFail()->status)->toBe(PayoutAttemptStatus::Failed)
        ->and($retrying->ledgerEntries()->where('type', WalletLedgerEntryType::AdjustmentCredit)->count())->toBe(0);

    config()->set('lartisan.payouts.max_attempts', 1);
    config()->set('services.paystack.payment_url', 'https://dispatch-terminal-failure.test');
    Http::fake([
        'https://dispatch-terminal-failure.test/transfer' => Http::response([
            'status' => false,
            'message' => 'Terminal provider failure.',
            'data' => ['status' => 'failed'],
        ], 400),
    ]);
    $terminalContext = phaseFourteenApprovedPayout();
    $terminal = app(DispatchPayoutTransfer::class)->handle($terminalContext['payout']);
    $releaseReference = 'payout-'.$terminal->id.'-failed-release';
    $existingRelease = $terminal->ledgerEntries()->where('immutable_reference', $releaseReference)->firstOrFail();
    $existingCase = $terminal->supportCases()->where('subject', 'Payout transfer failed')->firstOrFail();
    $secondRelease = app(ReleaseFailedPayoutBalance::class)->handle(
        payout: $terminal,
        immutableReference: $releaseReference,
        description: 'Released failed payout debit',
    );
    $duplicateCase = app(CreatePayoutExceptionCase::class)->handle(
        payout: $terminal,
        subject: 'Payout transfer failed',
    );

    expect($terminal->status)->toBe(PayoutStatus::Failed)
        ->and($terminal->supportCases()->where('subject', 'Payout transfer failed')->count())->toBe(1)
        ->and($secondRelease?->id)->toBe($existingRelease->id)
        ->and($duplicateCase->id)->toBe($existingCase->id);

    config()->set('lartisan.payouts.max_attempts', 3);
    config()->set('services.paystack.payment_url', 'https://dispatch-data-action.test');
    Http::fake([
        'https://dispatch-data-action.test/transfer' => Http::response([
            'status' => true,
            'message' => 'Transfer queued',
            'data' => [
                'reference' => 'provider-data-action',
                'status' => 'otp_required',
                'transfer_code' => 'TRF_action',
            ],
        ]),
    ]);
    $actionContext = phaseFourteenApprovedPayout();
    $actionRequired = app(DispatchPayoutTransfer::class)->handle($actionContext['payout']);

    expect($actionRequired->status)->toBe(PayoutStatus::InReview)
        ->and($actionRequired->attempts()->firstOrFail()->status)->toBe(PayoutAttemptStatus::ActionRequired);
});

test('payout reconciliation handles pending action required retry and unpaid reversal states', function () {
    $reconciler = app(ReconcilePayoutTransfer::class);

    $actionContext = phaseFourteenDispatchedPayout('TRF_action_required');
    $actionRequired = $reconciler->handle($actionContext['payout'], new TransferVerification(
        reference: (string) $actionContext['payout']->provider_reference,
        transferCode: $actionContext['payout']->provider_transfer_code,
        providerStatus: 'otp_required',
        failureReason: null,
        raw: ['status' => 'otp_required'],
    ));

    expect($actionRequired->status)->toBe(PayoutStatus::InReview)
        ->and($actionRequired->attempts()->firstOrFail()->status)->toBe(PayoutAttemptStatus::ActionRequired)
        ->and($actionRequired->supportCases()->where('subject', 'Payout transfer requires finance action')->count())->toBe(1);

    config()->set('lartisan.payouts.retry_delay_minutes', 'bad-config');
    $pendingContext = phaseFourteenDispatchedPayout('TRF_pending');
    $pending = $reconciler->handle($pendingContext['payout'], new TransferVerification(
        reference: 'provider-pending-fallback',
        transferCode: null,
        providerStatus: 'pending',
        failureReason: null,
        raw: ['status' => 'pending'],
    ));

    expect($pending->status)->toBe(PayoutStatus::Processing)
        ->and($pending->provider_status)->toBe('pending')
        ->and($pending->attempts()->firstOrFail()->status)->toBe(PayoutAttemptStatus::Uncertain)
        ->and($pending->next_retry_at)->not->toBeNull();

    config()->set('lartisan.payouts.max_attempts', 3);
    $failedContext = phaseFourteenDispatchedPayout('TRF_failed_nonterminal');
    $failed = $reconciler->handle($failedContext['payout'], new TransferVerification(
        reference: 'provider-failed-fallback',
        transferCode: null,
        providerStatus: 'failed',
        failureReason: null,
        raw: ['status' => 'failed'],
    ));

    expect($failed->status)->toBe(PayoutStatus::Retrying)
        ->and($failed->failure_reason)->toBe('Provider transfer failed.')
        ->and($failed->attempts()->firstOrFail()->status)->toBe(PayoutAttemptStatus::Failed);

    $noAttemptContext = phaseFourteenApprovedPayout();
    $noAttemptContext['payout']->forceFill([
        'provider_reference' => 'provider-no-attempt',
        'status' => PayoutStatus::Processing,
    ])->save();
    $noAttemptFailed = $reconciler->handle($noAttemptContext['payout'], new TransferVerification(
        reference: 'provider-no-attempt',
        transferCode: null,
        providerStatus: 'failed',
        failureReason: 'No attempt row.',
        raw: ['status' => 'failed'],
    ));

    expect($noAttemptFailed->status)->toBe(PayoutStatus::Retrying)
        ->and($noAttemptFailed->failure_reason)->toBe('No attempt row.');

    $reversedContext = phaseFourteenDispatchedPayout('TRF_reversed_unpaid');
    $reversed = $reconciler->handle($reversedContext['payout'], new TransferVerification(
        reference: (string) $reversedContext['payout']->provider_reference,
        transferCode: $reversedContext['payout']->provider_transfer_code,
        providerStatus: 'reversed',
        failureReason: null,
        raw: ['status' => 'reversed'],
    ));

    expect($reversed->status)->toBe(PayoutStatus::Failed)
        ->and($reversed->ledgerEntries()->where('immutable_reference', 'payout-'.$reversed->id.'-failed-release')->count())->toBe(1)
        ->and($reversed->supportCases()->where('subject', 'Payout transfer reversed')->count())->toBe(1);
});

test('paystack transfer webhooks reject missing unmatched and mismatched payloads', function () {
    $missingReferencePayload = [
        'event' => 'transfer.success',
        'data' => [
            'id' => 'missing-reference',
            'amount' => 100000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    postJson(route('webhooks.paystack'), $missingReferencePayload, [
        'x-paystack-signature' => phaseFourteenSignature($missingReferencePayload),
    ])->assertOk();

    expect(ProviderWebhookEvent::query()->where('provider_event_id', 'transfer.success:missing-reference')->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Failed);

    $unknownPayload = [
        'event' => 'transfer.success',
        'data' => [
            'id' => 'unknown-reference',
            'amount' => 100000,
            'currency' => 'NGN',
            'reference' => 'missing-local-payout',
            'status' => 'success',
        ],
    ];

    postJson(route('webhooks.paystack'), $unknownPayload, [
        'x-paystack-signature' => phaseFourteenSignature($unknownPayload),
    ])->assertOk();

    expect(ProviderWebhookEvent::query()->where('provider_event_id', 'transfer.success:unknown-reference')->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Ignored);

    $mismatchContext = phaseFourteenDispatchedPayout('TRF_mismatch');
    $mismatchPayload = phaseFourteenTransferWebhookPayload('transfer.success', $mismatchContext['payout'], 'success');
    $mismatchPayload = [
        ...$mismatchPayload,
        'data' => [
            ...phaseFourteenTransferWebhookData($mismatchPayload),
            'amount' => $mismatchContext['payout']->amount + 1,
            'id' => 'mismatched-amount',
        ],
    ];

    postJson(route('webhooks.paystack'), $mismatchPayload, [
        'x-paystack-signature' => phaseFourteenSignature($mismatchPayload),
    ])->assertOk();

    expect(ProviderWebhookEvent::query()->where('provider_event_id', 'transfer.success:mismatched-amount')->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Failed);

    $codeOnlyContext = phaseFourteenDispatchedPayout('TRF_code_only');
    $codeOnlyPayload = phaseFourteenTransferWebhookPayload('transfer.success', $codeOnlyContext['payout'], 'success');
    $codeOnlyData = phaseFourteenTransferWebhookData($codeOnlyPayload);
    unset($codeOnlyData['reference']);
    $codeOnlyPayload = [
        ...$codeOnlyPayload,
        'data' => [
            ...$codeOnlyData,
            'id' => 'code-only',
        ],
    ];

    postJson(route('webhooks.paystack'), $codeOnlyPayload, [
        'x-paystack-signature' => phaseFourteenSignature($codeOnlyPayload),
    ])->assertOk();

    expect($codeOnlyContext['payout']->refresh()->status)->toBe(PayoutStatus::Paid)
        ->and(ProviderWebhookEvent::query()->where('provider_event_id', 'transfer.success:code-only')->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Processed);
});

test('payout commands cover empty and provider exception paths', function () {
    expect(Artisan::call('payouts:dispatch-approved', ['--limit' => 'bad']))
        ->toBe(0)
        ->and(Artisan::output())->toContain('No approved payouts are eligible for dispatch.');

    $dispatchContext = phaseFourteenApprovedPayout();
    $dispatchDouble = new class($dispatchContext['payout']) extends DispatchPayoutTransfer
    {
        public ?PayoutBatch $seenBatch = null;

        public function __construct(private readonly Payout $expectedPayout) {}

        public function handle(Payout $payout, ?User $actor = null, ?PayoutBatch $batch = null): Payout
        {
            expect($payout->is($this->expectedPayout))->toBeTrue();

            $this->seenBatch = $batch;

            throw new RuntimeException('Provider exploded.');
        }
    };
    app()->instance(DispatchPayoutTransfer::class, $dispatchDouble);

    expect(Artisan::call('payouts:dispatch-approved', ['--limit' => 1]))
        ->toBe(0)
        ->and(Artisan::output())->toContain('could not be dispatched: Provider exploded.');
    expect($dispatchDouble->seenBatch)->toBeInstanceOf(PayoutBatch::class);

    expect(Artisan::call('payouts:reconcile-processing', ['--limit' => 'bad', '--stale-minutes' => 'bad']))
        ->toBe(0)
        ->and(Artisan::output())->toContain('No processing payouts are stale enough for reconciliation.');

    $staleContext = phaseFourteenApprovedPayout();
    $stalePayout = $staleContext['payout'];
    $stalePayout->forceFill([
        'processing_at' => now()->subMinutes(10),
        'provider_reference' => 'stale-provider-reference',
        'status' => PayoutStatus::Processing,
    ])->save();

    app()->instance(PayoutProvider::class, new class implements PayoutProvider
    {
        public function resolveBankAccount(PayoutAccount $account): BankAccountResolution
        {
            throw new BadMethodCallException('Not used.');
        }

        public function createTransferRecipient(PayoutAccount $account): TransferRecipient
        {
            throw new BadMethodCallException('Not used.');
        }

        public function initiateTransfer(Payout $payout, PayoutAttempt $attempt): TransferDispatch
        {
            throw new BadMethodCallException('Not used.');
        }

        public function verifyTransfer(string $reference): TransferVerification
        {
            throw new PayoutProviderException('Provider verification unavailable.', ['reference' => $reference]);
        }

        public function webhookSignatureIsValid(string $payload, ?string $signature): bool
        {
            return true;
        }
    });

    expect(Artisan::call('payouts:reconcile-processing', ['--limit' => 1, '--stale-minutes' => 1]))
        ->toBe(0)
        ->and(Artisan::output())->toContain('could not be verified: Provider verification unavailable.');

    expect($stalePayout->supportCases()->where('subject', 'Payout transfer needs reconciliation')->count())->toBe(1);
});

test('dispatch command completes batches with exception counts', function () {
    config()->set('services.paystack.payment_url', 'https://dispatch-command-exception.test');
    Http::preventStrayRequests();
    Http::fake([
        'https://dispatch-command-exception.test/bank/resolve*' => Http::response([
            'status' => false,
            'message' => 'Account rejected for command batch.',
        ], 422),
    ]);
    $context = phaseFourteenApprovedPayout(verifiedAccount: false);

    expect(Artisan::call('payouts:dispatch-approved', ['--limit' => 1]))
        ->toBe(0)
        ->and(Artisan::output())->toContain('Processed payout batch');

    $batch = $context['payout']->refresh()->batch()->firstOrFail();

    expect($batch->status)->toBe(PayoutBatchStatus::CompletedWithExceptions)
        ->and($batch->action_required_payouts)->toBe(1)
        ->and($batch->successful_payouts)->toBe(0);
});

test('filament payout table exposes exception filter and dispatch controls', function () {
    $manager = phaseFourteenFinanceUser();
    $exceptionContext = phaseFourteenApprovedPayout();
    $exceptionPayout = $exceptionContext['payout'];
    $exceptionPayout->forceFill([
        'provider_status' => 'uncertain',
        'status' => PayoutStatus::Processing,
    ])->save();
    $normalContext = phaseFourteenApprovedPayout();
    $normalPayout = $normalContext['payout'];
    $normalPayout->forceFill([
        'provider_status' => 'success',
        'status' => PayoutStatus::Paid,
    ])->save();

    Filament::setCurrentPanel('admin');
    Livewire::actingAs($manager);

    Livewire::test(ListPayouts::class)
        ->filterTable('exceptions')
        ->assertCanSeeTableRecords([$exceptionPayout])
        ->assertCanNotSeeTableRecords([$normalPayout]);

    Livewire::test(ListPayouts::class)
        ->callAction(TestAction::make('dispatchBatch')->table())
        ->assertHasNoActionErrors();

    $dispatchContext = phaseFourteenApprovedPayout();
    $dispatchPayout = $dispatchContext['payout'];
    $dispatchDouble = new class($dispatchPayout, $manager) extends DispatchPayoutTransfer
    {
        public bool $called = false;

        public function __construct(
            private readonly Payout $expectedPayout,
            private readonly User $expectedActor,
        ) {}

        public function handle(Payout $payout, ?User $actor = null, ?PayoutBatch $batch = null): Payout
        {
            expect($payout->is($this->expectedPayout))->toBeTrue()
                ->and($actor?->is($this->expectedActor))->toBeTrue();

            $this->called = true;

            return $this->expectedPayout;
        }
    };
    app()->instance(DispatchPayoutTransfer::class, $dispatchDouble);

    Livewire::test(ListPayouts::class)
        ->callTableAction('dispatch', (string) $dispatchPayout->id)
        ->assertHasNoTableActionErrors();
    expect($dispatchDouble->called)->toBeTrue();
});

test('manual payout success requires the reserved wallet debit', function () {
    $manager = phaseFourteenFinanceUser();
    $context = phaseFourteenApprovedPayout();
    $payout = $context['payout'];
    $payout->ledgerEntries()->delete();
    $payout->forceFill([
        'provider_status' => 'missing_debit',
        'status' => PayoutStatus::InReview,
    ])->save();

    expect(fn () => app(ProcessPayout::class)->handle(
        payout: $payout->refresh(),
        processor: $manager,
        successful: true,
        providerReference: 'manual-'.$payout->id,
        providerPayload: ['source' => 'test'],
    ))->toThrow(InvalidArgumentException::class, 'Successful payout processing requires a reserved wallet debit.');

    expect($payout->refresh()->status)->toBe(PayoutStatus::InReview)
        ->and($payout->attempts()->count())->toBe(0)
        ->and($payout->ledgerEntries()->where('type', WalletLedgerEntryType::PayoutDebit)->count())->toBe(0);

    Filament::setCurrentPanel('admin');
    Livewire::actingAs($manager);

    Livewire::test(ListPayouts::class)
        ->assertTableActionHidden('process', (string) $payout->id);
});

/**
 * @return array{owner: User, team: Team, profile: ArtisanProfile, wallet: Wallet, account: PayoutAccount, payout: Payout}
 */
function phaseFourteenApprovedPayout(bool $verifiedAccount = true): array
{
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $owner->givePermissionTo(PlatformPermission::ViewOwnWallet->value);
    $profile = $context['profile'];
    $wallet = Wallet::factory()->create([
        'artisan_profile_id' => $profile->id,
        'available_balance' => 500000,
    ]);
    $account = ($verifiedAccount ? PayoutAccount::factory()->verified() : PayoutAccount::factory())->create([
        'account_name' => 'Original Artisan',
        'account_number' => '0123456789',
        'artisan_profile_id' => $profile->id,
        'provider' => PaymentProviderName::Paystack,
    ]);
    $payout = Payout::factory()->approved(phaseFourteenFinanceUser())->create([
        'amount' => 100000,
        'artisan_profile_id' => $profile->id,
        'currency_code' => 'NGN',
        'payout_account_id' => $account->id,
        'requested_by' => $owner->id,
        'wallet_id' => $wallet->id,
    ]);

    WalletLedgerEntry::factory()->create([
        'amount' => $payout->amount,
        'available_balance_after' => 400000,
        'description' => 'Approved payout debit',
        'direction' => WalletLedgerDirection::Debit,
        'immutable_reference' => 'payout-'.$payout->id.'-debit',
        'source_id' => $payout->id,
        'source_type' => $payout->getMorphClass(),
        'type' => WalletLedgerEntryType::PayoutDebit,
        'wallet_id' => $wallet->id,
    ]);
    $wallet->forceFill(['available_balance' => 400000])->save();

    return [
        'owner' => $owner,
        'team' => $context['team'],
        'profile' => $profile,
        'wallet' => $wallet->refresh(),
        'account' => $account->refresh(),
        'payout' => $payout->refresh(),
    ];
}

/**
 * @return array{owner: User, team: Team, profile: ArtisanProfile, wallet: Wallet, account: PayoutAccount, payout: Payout}
 */
function phaseFourteenDispatchedPayout(string $transferCode = 'TRF_dispatched'): array
{
    $baseUrl = 'https://paystack-dispatch-'.substr(md5($transferCode), 0, 12).'.test';

    config()->set('services.paystack.payment_url', $baseUrl);
    Http::preventStrayRequests();
    Http::fake([
        $baseUrl.'/transfer' => fn (Request $request) => Http::response([
            'status' => true,
            'message' => 'Transfer queued',
            'data' => [
                'reference' => $request['reference'],
                'status' => 'pending',
                'transfer_code' => $transferCode,
            ],
        ]),
    ]);
    $context = phaseFourteenApprovedPayout();

    app(DispatchPayoutTransfer::class)->handle($context['payout']);

    return [
        ...$context,
        'payout' => $context['payout']->refresh(),
    ];
}

function phaseFourteenFinanceUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(
        PlatformPermission::ManagePayouts->value,
        PlatformPermission::ViewGlobalReports->value,
    );

    return $user;
}

/**
 * @return array<string, mixed>
 */
function phaseFourteenTransferWebhookPayload(
    string $event,
    Payout $payout,
    string $status,
    ?string $failureReason = null,
): array {
    return [
        'event' => $event,
        'data' => [
            'id' => $payout->id,
            'amount' => $payout->amount,
            'currency' => $payout->currency_code,
            'failure_reason' => $failureReason,
            'reference' => $payout->provider_reference,
            'status' => $status,
            'transfer_code' => $payout->provider_transfer_code,
        ],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function phaseFourteenTransferWebhookData(array $payload): array
{
    $data = $payload['data'] ?? [];

    if (! is_array($data)) {
        return [];
    }

    /** @var array<string, mixed> $data */
    return $data;
}

/**
 * @param  array<string, mixed>  $payload
 */
function phaseFourteenSignature(array $payload): string
{
    return hash_hmac('sha512', json_encode($payload, JSON_THROW_ON_ERROR), 'test-secret');
}
