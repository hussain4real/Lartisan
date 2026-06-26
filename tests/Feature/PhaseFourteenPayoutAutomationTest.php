<?php

use App\Actions\Payouts\DispatchPayoutTransfer;
use App\Enums\PaymentProviderName;
use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutBatchStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\SupportCaseCategory;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\PayoutBatch;
use App\Models\ProviderWebhookEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

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
function phaseFourteenDispatchedPayout(): array
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/transfer' => fn (Request $request) => Http::response([
            'status' => true,
            'message' => 'Transfer queued',
            'data' => [
                'reference' => $request['reference'],
                'status' => 'pending',
                'transfer_code' => 'TRF_dispatched',
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
 */
function phaseFourteenSignature(array $payload): string
{
    return hash_hmac('sha512', json_encode($payload, JSON_THROW_ON_ERROR), 'test-secret');
}
