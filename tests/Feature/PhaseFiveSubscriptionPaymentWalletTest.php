<?php

use App\Actions\Payments\ActivateSubscription;
use App\Actions\Payments\InitializePayment;
use App\Actions\Payments\PostWalletLedgerEntry;
use App\Actions\Payments\ProcessPaystackWebhook;
use App\Contracts\Payments\PaymentProvider;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\ProviderWebhookEventStatus;
use App\Enums\SubscriptionInterval;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\ArtisanProfile;
use App\Models\Payment;
use App\Models\PayoutAccount;
use App\Models\ProviderWebhookEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Services\Payments\PaystackPaymentProvider;
use App\Support\Payments\PaymentInitialization;
use Database\Seeders\PilotUserSeeder;
use Database\Seeders\PlatformAccessSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

beforeEach(function () {
    $this->withoutVite();
    config()->set('services.paystack.secret_key', 'sk_test_phase_five');
    config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    $this->seed(PilotUserSeeder::class);
});

/**
 * @return array{artisan: User, customer: User, profile: ArtisanProfile, team: Team, plan: SubscriptionPlan}
 */
function phaseFiveContext(): array
{
    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();
    $profile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();
    $team = $profile->team()->firstOrFail();
    $plan = SubscriptionPlan::query()->where('slug', 'starter-listing')->firstOrFail();

    return [
        'artisan' => $artisan,
        'customer' => $customer,
        'profile' => $profile,
        'team' => $team,
        'plan' => $plan,
    ];
}

/**
 * @param  array<string, mixed>  $data
 * @return array{payload: string, signature: string}
 */
function phaseFiveSignedPayload(string $event, array $data): array
{
    $payload = json_encode(['event' => $event, 'data' => $data], JSON_THROW_ON_ERROR);

    return [
        'payload' => $payload,
        'signature' => hash_hmac('sha512', $payload, 'sk_test_phase_five'),
    ];
}

/**
 * @return TestResponse<JsonResponse>
 */
function phaseFiveWebhook(TestCase $testCase, string $payload, string $signature): TestResponse
{
    return $testCase->call(
        'POST',
        '/webhooks/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ],
        $payload,
    );
}

test('phase five seed data permissions models and relationships are wired', function () {
    /** @var TestCase $this */
    $context = phaseFiveContext();
    $profile = $context['profile'];
    $artisan = $context['artisan'];
    $customer = $context['customer'];
    $plan = $context['plan'];

    $wallet = Wallet::factory()->create(['artisan_profile_id' => $profile->id]);
    $payment = Payment::factory()->successful()->create([
        'artisan_profile_id' => $profile->id,
        'amount' => $plan->price_amount,
        'currency_code' => $plan->currency_code,
        'subscription_plan_id' => $plan->id,
    ]);
    $subscription = Subscription::factory()->active($payment)->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);
    $payment->forceFill(['subscription_id' => $subscription->id])->save();
    $ledgerEntry = WalletLedgerEntry::factory()->create([
        'source_id' => $payment->id,
        'source_type' => $payment->getMorphClass(),
        'wallet_id' => $wallet->id,
    ]);
    $payoutAccount = PayoutAccount::factory()->verified()->create([
        'account_number' => '0123456789',
        'artisan_profile_id' => $profile->id,
    ]);
    $event = ProviderWebhookEvent::factory()->processed()->create(['reference' => $payment->reference]);

    expect(SubscriptionPlan::query()->count())->toBe(3);
    expect(PaymentProviderName::cases())->toHaveCount(2);
    expect(PaymentPurpose::cases())->toBe([PaymentPurpose::Subscription]);
    expect(PaymentStatus::cases())->toHaveCount(4);
    expect(PayoutAccountStatus::cases())->toHaveCount(4);
    expect(ProviderWebhookEventStatus::cases())->toHaveCount(4);
    expect(SubscriptionInterval::cases())->toHaveCount(3);
    expect(SubscriptionStatus::cases())->toHaveCount(5);
    expect(WalletLedgerDirection::cases())->toHaveCount(2);
    expect(WalletLedgerEntryType::cases())->toHaveCount(7);
    expect($artisan->can('artisan.subscription.manage'))->toBeTrue();
    expect(Gate::forUser($artisan)->allows('manageSubscription', $profile))->toBeTrue();
    expect(Gate::forUser($artisan)->allows('viewWallet', $profile))->toBeTrue();
    expect(Gate::forUser($customer)->allows('viewWallet', $profile))->toBeFalse();
    expect($plan->interval)->toBe(SubscriptionInterval::Monthly);
    expect($plan->active)->toBeTrue();
    expect($plan->subscriptions()->firstOrFail()->is($subscription))->toBeTrue();
    expect($plan->payments()->firstOrFail()->is($payment))->toBeTrue();
    expect($subscription->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($subscription->plan()->firstOrFail()->is($plan))->toBeTrue();
    expect($subscription->payment()->firstOrFail()->is($payment))->toBeTrue();
    expect($payment->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($payment->plan()->firstOrFail()->is($plan))->toBeTrue();
    expect($payment->subscription()->firstOrFail()->is($subscription))->toBeTrue();
    expect($profile->subscriptions()->firstOrFail()->is($subscription))->toBeTrue();
    expect($profile->activeSubscription()->firstOrFail()->is($subscription))->toBeTrue();
    expect($profile->payments()->firstOrFail()->is($payment))->toBeTrue();
    expect($profile->wallet()->firstOrFail()->is($wallet))->toBeTrue();
    expect($profile->payoutAccounts()->firstOrFail()->is($payoutAccount))->toBeTrue();
    expect($wallet->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($wallet->ledgerEntries()->firstOrFail()->is($ledgerEntry))->toBeTrue();
    expect($ledgerEntry->wallet()->firstOrFail()->is($wallet))->toBeTrue();
    expect($ledgerEntry->source()->firstOrFail()->is($payment))->toBeTrue();
    expect($payoutAccount->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($payoutAccount->status)->toBe(PayoutAccountStatus::Verified);
    expect($payoutAccount->account_number)->toBe('0123456789');
    expect(DB::table('payout_accounts')->where('id', $payoutAccount->id)->value('account_number'))->not->toBe('0123456789');
    expect($event->status)->toBe(ProviderWebhookEventStatus::Processed);
});

test('artisan subscription and wallet pages expose inertia contracts', function () {
    /** @var TestCase $this */
    $context = phaseFiveContext();
    $artisan = $context['artisan'];
    $profile = $context['profile'];
    $team = $context['team'];
    $plan = $context['plan'];

    $this->actingAs($artisan)
        ->get(route('artisan.subscription.show', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Subscription')
            ->where('profile.businessName', 'Wuse Sparks Electrical')
            ->where('plans.0.slug', 'starter-listing')
            ->where('currentSubscription', null)
            ->where('recentPayments', []));

    $this->actingAs($artisan)
        ->get(route('artisan.wallet.show', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Wallet')
            ->where('wallet.availableBalance', 0)
            ->where('ledgerEntries', [])
            ->where('payoutAccounts', []));

    $wallet = Wallet::factory()->create(['artisan_profile_id' => $profile->id]);
    $payment = Payment::factory()->successful()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);
    app(PostWalletLedgerEntry::class)->handle(
        wallet: $wallet,
        type: WalletLedgerEntryType::BookingCredit,
        direction: WalletLedgerDirection::Credit,
        amount: 750000,
        source: $payment,
        immutableReference: 'wallet-contract-credit',
        description: 'Booking payout',
        metadata: ['booking' => 'demo'],
    );
    $subscription = Subscription::factory()->active($payment)->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);
    $payment->forceFill(['subscription_id' => $subscription->id])->save();
    PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $profile->id]);

    $this->actingAs($artisan)
        ->get(route('artisan.subscription.show', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Subscription')
            ->where('currentSubscription.plan.slug', 'starter-listing')
            ->where('recentPayments.0.reference', $payment->reference));

    $this->actingAs($artisan)
        ->get(route('artisan.wallet.show', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Wallet')
            ->where('wallet.availableBalance', 750000)
            ->where('ledgerEntries.0.immutableReference', 'wallet-contract-credit')
            ->where('payoutAccounts.0.status', PayoutAccountStatus::Verified->value));
});

test('initializing a subscription payment creates a paystack checkout and handles provider failures', function () {
    /** @var TestCase $this */
    $context = phaseFiveContext();
    $artisan = $context['artisan'];
    $profile = $context['profile'];
    $team = $context['team'];
    $plan = $context['plan'];

    Http::fake([
        'https://api.paystack.co/*' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/phase-five',
                'access_code' => 'phase-five-access',
                'reference' => 'phase-five-provider-reference',
            ],
        ]),
    ]);

    $this->actingAs($artisan)
        ->withHeader('X-Inertia', 'true')
        ->post(route('artisan.subscription.store', ['current_team' => $team->slug]), [
            'subscription_plan_id' => $plan->id,
        ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/phase-five');

    $payment = Payment::query()->where('artisan_profile_id', $profile->id)->latest('id')->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Pending);
    expect($payment->checkout_url)->toBe('https://checkout.paystack.com/phase-five');
    expect($payment->access_code)->toBe('phase-five-access');
    expect($payment->provider_reference)->toBe('phase-five-provider-reference');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['amount'] === (string) $plan->price_amount
        && $request['currency'] === 'NGN'
        && $request['email'] === $artisan->email
        && $request['callback_url'] === route('artisan.subscription.show', ['current_team' => $team->slug]));

    $inactivePlan = SubscriptionPlan::factory()->inactive()->create();

    expect(fn () => app(InitializePayment::class)->handle($profile, $inactivePlan, 'https://lartisan.test/callback'))
        ->toThrow(InvalidArgumentException::class);

    $provider = new PaystackPaymentProvider;
    $providerPayment = Payment::factory()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);

    config()->set('services.paystack.payment_url', 'https://paystack-fail.test');
    Http::fake(['https://paystack-fail.test/*' => Http::response(['status' => false], 400)]);
    expect(fn () => $provider->initialize($providerPayment, 'https://lartisan.test/callback'))
        ->toThrow(RuntimeException::class, 'could not initialize');

    config()->set('services.paystack.payment_url', 'https://paystack-data.test');
    Http::fake(['https://paystack-data.test/*' => Http::response(['status' => true])]);
    expect(fn () => $provider->initialize($providerPayment, 'https://lartisan.test/callback'))
        ->toThrow(RuntimeException::class, 'invalid transaction initialization payload');

    config()->set('services.paystack.payment_url', 'https://paystack-credentials.test');
    Http::fake(['https://paystack-credentials.test/*' => Http::response(['status' => true, 'data' => []])]);
    expect(fn () => $provider->initialize($providerPayment, 'https://lartisan.test/callback'))
        ->toThrow(RuntimeException::class, 'checkout credentials');

    app()->instance(PaymentProvider::class, new class implements PaymentProvider
    {
        public function initialize(Payment $payment, string $callbackUrl): PaymentInitialization
        {
            throw new RuntimeException('Provider unavailable.');
        }

        public function webhookSignatureIsValid(string $payload, ?string $signature): bool
        {
            return false;
        }
    });

    expect(fn () => app(InitializePayment::class)->handle($profile, $plan, 'https://lartisan.test/callback'))
        ->toThrow(RuntimeException::class, 'Provider unavailable.');
    expect(Payment::query()->where('status', PaymentStatus::Failed)->count())->toBe(1);
});

test('paystack webhooks verify signatures activate subscriptions once and reject unsafe payloads', function () {
    /** @var TestCase $this */
    $context = phaseFiveContext();
    $profile = $context['profile'];
    $plan = $context['plan'];
    $profile->forceFill(['verification_status' => ArtisanVerificationStatus::Approved])->save();
    $payment = Payment::factory()->create([
        'amount' => $plan->price_amount,
        'artisan_profile_id' => $profile->id,
        'currency_code' => $plan->currency_code,
        'reference' => 'lartisan-success-reference',
        'subscription_plan_id' => $plan->id,
    ]);
    $signed = phaseFiveSignedPayload('charge.success', [
        'amount' => (string) $payment->amount,
        'currency' => 'NGN',
        'gateway_response' => 'Successful',
        'id' => 9001,
        'reference' => $payment->reference,
        'status' => 'success',
    ]);

    app(PaymentProvider::class)->webhookSignatureIsValid($signed['payload'], $signed['signature']);
    expect((new PaystackPaymentProvider)->webhookSignatureIsValid('payload', null))->toBeFalse();
    phaseFiveWebhook($this, $signed['payload'], $signed['signature'])->assertOk()->assertJson(['status' => 'ok']);
    phaseFiveWebhook($this, $signed['payload'], $signed['signature'])->assertOk();

    $payment->refresh();
    $profile->refresh();

    expect($payment->status)->toBe(PaymentStatus::Successful);
    expect($payment->subscription()->exists())->toBeTrue();
    expect($profile->subscription_status)->toBe(ArtisanSubscriptionStatus::Active);
    expect($profile->is_public)->toBeTrue();
    expect($profile->wallet()->exists())->toBeTrue();
    expect(Subscription::query()->where('payment_id', $payment->id)->count())->toBe(1);
    expect(ProviderWebhookEvent::query()->where('reference', $payment->reference)->count())->toBe(1);
    expect(app(ActivateSubscription::class)->handle($payment)->id)->toBe($payment->subscription_id);

    $pendingPayment = Payment::factory()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);

    expect(fn () => app(ActivateSubscription::class)->handle($pendingPayment))
        ->toThrow(InvalidArgumentException::class);

    $mismatchedPayment = Payment::factory()->create([
        'amount' => $plan->price_amount,
        'artisan_profile_id' => $profile->id,
        'currency_code' => 'NGN',
        'reference' => 'lartisan-mismatch-reference',
        'subscription_plan_id' => $plan->id,
    ]);
    $mismatched = phaseFiveSignedPayload('charge.success', [
        'amount' => $mismatchedPayment->amount + 100,
        'currency' => 'NGN',
        'id' => 9002,
        'reference' => $mismatchedPayment->reference,
        'status' => 'success',
    ]);

    phaseFiveWebhook($this, $mismatched['payload'], $mismatched['signature'])->assertOk();
    expect($mismatchedPayment->refresh()->status)->toBe(PaymentStatus::Pending);
    expect(ProviderWebhookEvent::query()->where('reference', $mismatchedPayment->reference)->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Failed);

    $missingReference = phaseFiveSignedPayload('charge.success', ['id' => 9003, 'status' => 'success']);
    phaseFiveWebhook($this, $missingReference['payload'], $missingReference['signature'])->assertOk();
    expect(ProviderWebhookEvent::query()->where('provider_event_id', '9003')->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Failed);

    $unknownReference = phaseFiveSignedPayload('charge.success', [
        'id' => 9004,
        'reference' => 'unknown-reference',
        'status' => 'success',
    ]);
    phaseFiveWebhook($this, $unknownReference['payload'], $unknownReference['signature'])->assertOk();
    expect(ProviderWebhookEvent::query()->where('provider_event_id', '9004')->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Ignored);

    $referenceOnlyEvent = ProviderWebhookEvent::factory()->processed()->create([
        'event' => 'charge.success',
        'provider_event_id' => null,
        'reference' => 'pre-existing-reference',
    ]);
    $preExistingReference = phaseFiveSignedPayload('charge.success', [
        'id' => 9005,
        'reference' => 'pre-existing-reference',
        'status' => 'success',
    ]);
    phaseFiveWebhook($this, $preExistingReference['payload'], $preExistingReference['signature'])->assertOk();
    expect($referenceOnlyEvent->refresh()->status)->toBe(ProviderWebhookEventStatus::Processed);

    $pendingStatusPayment = Payment::factory()->create([
        'artisan_profile_id' => $profile->id,
        'reference' => 'lartisan-pending-status',
        'subscription_plan_id' => $plan->id,
    ]);
    $pendingStatus = phaseFiveSignedPayload('charge.pending', [
        'id' => 9006,
        'reference' => $pendingStatusPayment->reference,
        'status' => 'pending',
    ]);
    phaseFiveWebhook($this, $pendingStatus['payload'], $pendingStatus['signature'])->assertOk();
    expect($pendingStatusPayment->refresh()->status)->toBe(PaymentStatus::Pending);

    $nonArrayDataPayload = json_encode(['event' => 'charge.success', 'data' => 'not-array'], JSON_THROW_ON_ERROR);
    app(ProcessPaystackWebhook::class)->handle(
        $nonArrayDataPayload,
        hash_hmac('sha512', $nonArrayDataPayload, 'sk_test_phase_five'),
    );

    $missingAmountPayment = Payment::factory()->create([
        'amount' => $plan->price_amount,
        'artisan_profile_id' => $profile->id,
        'currency_code' => 'NGN',
        'reference' => 'lartisan-missing-amount',
        'subscription_plan_id' => $plan->id,
    ]);
    $missingAmount = phaseFiveSignedPayload('charge.success', [
        'currency' => 'NGN',
        'id' => 9007,
        'reference' => $missingAmountPayment->reference,
        'status' => 'success',
    ]);
    phaseFiveWebhook($this, $missingAmount['payload'], $missingAmount['signature'])->assertOk();
    expect($missingAmountPayment->refresh()->status)->toBe(PaymentStatus::Pending);

    $invalidJson = '{';
    $invalidJsonSignature = hash_hmac('sha512', $invalidJson, 'sk_test_phase_five');
    expect(fn () => app(ProcessPaystackWebhook::class)->handle($invalidJson, $invalidJsonSignature))
        ->toThrow(BadRequestHttpException::class);

    $scalarJson = 'true';
    $scalarJsonSignature = hash_hmac('sha512', $scalarJson, 'sk_test_phase_five');
    expect(fn () => app(ProcessPaystackWebhook::class)->handle($scalarJson, $scalarJsonSignature))
        ->toThrow(BadRequestHttpException::class);

    expect(fn () => app(ProcessPaystackWebhook::class)->handle($signed['payload'], 'bad-signature'))
        ->toThrow(HttpException::class);
});

test('failed and abandoned payment webhooks are recorded without activating listing access', function () {
    /** @var TestCase $this */
    $context = phaseFiveContext();
    $profile = $context['profile'];
    $plan = $context['plan'];
    $failedPayment = Payment::factory()->create([
        'artisan_profile_id' => $profile->id,
        'reference' => 'lartisan-failed-reference',
        'subscription_plan_id' => $plan->id,
    ]);
    $failed = phaseFiveSignedPayload('charge.failed', [
        'gateway_response' => 'Declined',
        'id' => 9101,
        'reference' => $failedPayment->reference,
        'status' => 'failed',
    ]);

    phaseFiveWebhook($this, $failed['payload'], $failed['signature'])->assertOk();

    expect($failedPayment->refresh()->status)->toBe(PaymentStatus::Failed);
    expect($failedPayment->failure_reason)->toBe('Declined');
    expect($profile->refresh()->subscription_status)->not->toBe(ArtisanSubscriptionStatus::Active);
    expect($failedPayment->subscription()->exists())->toBeFalse();

    $abandonedPayment = Payment::factory()->create([
        'artisan_profile_id' => $profile->id,
        'reference' => 'lartisan-abandoned-reference',
        'subscription_plan_id' => $plan->id,
    ]);
    $abandoned = phaseFiveSignedPayload('charge.failed', [
        'gateway_response' => ['nested' => true],
        'id' => 9102,
        'reference' => $abandonedPayment->reference,
        'status' => 'abandoned',
    ]);

    phaseFiveWebhook($this, $abandoned['payload'], $abandoned['signature'])->assertOk();

    expect($abandonedPayment->refresh()->status)->toBe(PaymentStatus::Abandoned);
    expect($abandonedPayment->failure_reason)->toBe('Payment was not successful.');
});

test('wallet ledger entries post audited money movement and stay append only', function () {
    /** @var TestCase $this */
    $context = phaseFiveContext();
    $profile = $context['profile'];
    $wallet = Wallet::factory()->create(['artisan_profile_id' => $profile->id]);
    $payment = Payment::factory()->successful()->create(['artisan_profile_id' => $profile->id]);
    $postLedger = app(PostWalletLedgerEntry::class);

    $credit = $postLedger->handle(
        wallet: $wallet,
        type: WalletLedgerEntryType::AdjustmentCredit,
        direction: WalletLedgerDirection::Credit,
        amount: 250000,
        source: $payment,
        immutableReference: 'wallet-credit-reference',
        description: 'Manual credit',
        metadata: ['reason' => 'test'],
    );
    $debit = $postLedger->handle(
        wallet: $wallet->refresh(),
        type: WalletLedgerEntryType::AdjustmentDebit,
        direction: WalletLedgerDirection::Debit,
        amount: 75000,
        source: $payment,
        immutableReference: 'wallet-debit-reference',
        description: 'Manual debit',
        metadata: ['reason' => 'test'],
    );

    expect($credit->available_balance_after)->toBe(250000);
    expect($debit->available_balance_after)->toBe(175000);
    expect($wallet->refresh()->available_balance)->toBe(175000);
    expect($credit->source()->firstOrFail()->is($payment))->toBeTrue();

    $automaticReference = $postLedger->handle(
        wallet: $wallet->refresh(),
        type: WalletLedgerEntryType::AdjustmentCredit,
        direction: WalletLedgerDirection::Credit,
        amount: 25000,
        description: 'Automatic reference credit',
    );

    expect($automaticReference->immutable_reference)->toStartWith('wallet-');

    expect(fn () => $postLedger->handle($wallet->refresh(), WalletLedgerEntryType::FeeDebit, WalletLedgerDirection::Debit, 999999))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $postLedger->handle($wallet->refresh(), WalletLedgerEntryType::FeeDebit, WalletLedgerDirection::Debit, 0))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => WalletLedgerEntry::query()->create([
        'wallet_id' => $wallet->id,
        'type' => WalletLedgerEntryType::AdjustmentCredit,
        'direction' => WalletLedgerDirection::Credit,
        'amount' => 100,
        'available_balance_after' => 100,
        'pending_balance_after' => 0,
        'immutable_reference' => 'wallet-credit-reference',
        'posted_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => $credit->forceFill(['description' => 'Changed'])->save())->toThrow(LogicException::class);
    expect(fn () => $debit->delete())->toThrow(LogicException::class);
});

test('phase five seeders are idempotent and standalone', function () {
    /** @var TestCase $this */
    $this->seed(SubscriptionPlanSeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);
    $this->seed(PlatformAccessSeeder::class);

    expect(SubscriptionPlan::query()->count())->toBe(3);
    expect(User::query()->where('email', 'artisan@lartisan.test')->firstOrFail()->can('artisan.subscription.manage'))->toBeTrue();
});
