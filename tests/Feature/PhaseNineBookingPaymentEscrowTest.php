<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Bookings\AcceptBooking;
use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\FinishBookingWork;
use App\Actions\Bookings\ReleaseWalletBalance;
use App\Actions\Bookings\StartBookingWork;
use App\Actions\Payments\ActivateSubscription;
use App\Actions\Payments\CalculateBookingSettlement;
use App\Actions\Payments\EnsureWallet;
use App\Actions\Payments\EscrowBookingPayment;
use App\Actions\Payments\InitializeBookingPayment;
use App\Actions\Payments\RefundBookingPayment;
use App\Actions\Reviews\SubmitVerifiedReview;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\ProviderWebhookEventStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\Payment;
use App\Models\ProviderWebhookEvent;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('services.paystack.secret_key', 'sk_test_phase_nine');
    config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    config()->set('lartisan.booking_payments.commission_basis_points', 1000);
    config()->set('lartisan.booking_payments.provider_fee_basis_points', 150);
    config()->set('lartisan.booking_payments.provider_fee_flat_amount', 10000);
    $this->seed(PlatformAccessSeeder::class);
    $this->seed(GeographySeeder::class);
});

/**
 * @return array{owner: User, customer: User, profile: ArtisanProfile, team: Team, service: ArtisanService, country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseNineContext(): array
{
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
    $localGovernment = $territory->localGovernment()->firstOrFail();
    $state = $localGovernment->state()->firstOrFail();
    $country = $state->country()->firstOrFail();
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase Nine Repairs',
        'slug' => 'phase-nine-repairs',
    ]);
    $owner = User::factory()->create(['name' => 'Phase Nine Artisan']);
    $customer = User::factory()->create(['name' => 'Phase Nine Customer']);
    $profile = app(CreateArtisanBusinessProfile::class)->handle($owner, 'Phase Nine Electrical');
    $profile->forceFill([
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'country_id' => $country->id,
        'is_public' => true,
        'local_government_id' => $localGovernment->id,
        'state_id' => $state->id,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'territory_id' => $territory->id,
        'verification_status' => ArtisanVerificationStatus::Approved,
    ])->save();
    $service = ArtisanService::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'currency_code' => 'NGN',
        'service_category_id' => $category->id,
        'starting_price' => '25000.00',
        'title' => 'Phase Nine Repair',
    ]);
    Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
    ]);

    return [
        'owner' => $owner->refresh(),
        'customer' => $customer,
        'profile' => $profile->refresh(),
        'team' => $profile->team()->firstOrFail(),
        'service' => $service,
        'country' => $country,
        'state' => $state,
        'localGovernment' => $localGovernment,
        'territory' => $territory,
    ];
}

/**
 * @param  array{owner: User, customer: User, profile: ArtisanProfile, team: Team, service: ArtisanService, country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}  $context
 */
function phaseNineAcceptedBooking(array $context, ?User $customer = null): Booking
{
    $created = app(CreateBooking::class)->handle(
        profile: $context['profile'],
        service: $context['service'],
        customer: $customer ?? $context['customer'],
        customerName: 'Phase Nine Customer',
        customerPhone: '+2348039991111',
        customerEmail: 'phase-nine@example.test',
        addressSnapshot: [
            'line_1' => '12 Phase Nine Close',
            'country_id' => $context['country']->id,
            'state_id' => $context['state']->id,
            'local_government_id' => $context['localGovernment']->id,
            'territory_id' => $context['territory']->id,
        ],
        scheduledAt: now()->addDay(),
        description: 'Repair the main switch.',
    );

    return app(AcceptBooking::class)->handle($created->booking, $context['owner']);
}

/**
 * @param  array<string, mixed>  $data
 * @return array{payload: string, signature: string}
 */
function phaseNineSignedPayload(string $event, array $data): array
{
    $payload = json_encode(['event' => $event, 'data' => $data], JSON_THROW_ON_ERROR);

    return [
        'payload' => $payload,
        'signature' => hash_hmac('sha512', $payload, 'sk_test_phase_nine'),
    ];
}

function phaseNineFakePaystack(string $authorizationUrl = 'https://checkout.paystack.com/phase-nine'): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => [
                'access_code' => 'phase-nine-access',
                'authorization_url' => $authorizationUrl,
                'reference' => 'phase-nine-provider-reference',
            ],
        ]),
    ]);
}

/**
 * @return TestResponse<Response>
 */
function phaseNineWebhook(TestCase $test, string $payload, string $signature): TestResponse
{
    return $test->call(
        'POST',
        route('webhooks.paystack'),
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

test('customers and guests can initialize booking payment checkout with settlement snapshot', function (): void {
    /** @var TestCase $this */
    $context = phaseNineContext();
    $booking = phaseNineAcceptedBooking($context);
    phaseNineFakePaystack();

    $this
        ->actingAs($context['customer'])
        ->withHeader('X-Inertia', 'true')
        ->post(route('customer.bookings.payments.store', ['booking' => $booking]))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/phase-nine');

    $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($payment->purpose)->toBe(PaymentPurpose::Booking);
    expect($payment->amount)->toBe(2500000);
    expect($payment->commission_amount)->toBe(250000);
    expect($payment->provider_fee_amount)->toBe(47500);
    expect($payment->net_amount)->toBe(2202500);
    expect($booking->refresh()->payment_started_at)->not->toBeNull();

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['amount'] === '2500000'
        && $request['callback_url'] === route('customer.bookings.show', ['booking' => $booking]));

    $this
        ->actingAs($context['customer'])
        ->withHeader('X-Inertia', 'true')
        ->post(route('customer.bookings.payments.store', ['booking' => $booking]))
        ->assertStatus(409);

    expect(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1);

    $guestBooking = phaseNineAcceptedBooking($context, customer: null);
    $trackerToken = 'phase-nine-token';
    $guestBooking->forceFill(['secure_token_hash' => hash('sha256', $trackerToken)])->save();

    $this
        ->withHeader('X-Inertia', 'true')
        ->post(route('booking-tracker.payments.store', ['trackerCode' => $guestBooking->tracker_code]), [
            'token' => $trackerToken,
        ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/phase-nine');
});

test('paystack booking webhook escrows payment and customer confirmation settles net wallet balance', function (): void {
    /** @var TestCase $this */
    $context = phaseNineContext();
    $booking = phaseNineAcceptedBooking($context);
    phaseNineFakePaystack();
    $payment = app(InitializeBookingPayment::class)->handle(
        booking: $booking,
        callbackUrl: route('customer.bookings.show', ['booking' => $booking]),
    );
    $signed = phaseNineSignedPayload('charge.success', [
        'amount' => (string) $payment->amount,
        'currency' => 'NGN',
        'id' => 9901,
        'reference' => $payment->reference,
        'status' => 'success',
    ]);

    expect(fn () => app(StartBookingWork::class)->handle($booking, $context['owner']))
        ->toThrow(InvalidArgumentException::class, 'Only escrowed bookings can be started.');

    phaseNineWebhook($this, $signed['payload'], $signed['signature'])->assertOk();
    phaseNineWebhook($this, $signed['payload'], $signed['signature'])->assertOk();

    $booking->refresh();
    $wallet = $context['profile']->wallet()->firstOrFail();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful);
    expect($booking->status)->toBe(BookingStatus::Escrowed);
    expect($booking->paid_at)->not->toBeNull();
    expect($booking->escrowed_at)->not->toBeNull();
    expect($wallet->available_balance)->toBe(0);
    expect($wallet->pending_balance)->toBe(2202500);
    expect(WalletLedgerEntry::query()->where('source_id', $booking->id)->where('type', WalletLedgerEntryType::BookingCredit)->count())->toBe(1);
    expect(WalletLedgerEntry::query()->where('source_id', $booking->id)->where('type', WalletLedgerEntryType::CommissionDebit)->count())->toBe(1);
    expect(WalletLedgerEntry::query()->where('source_id', $booking->id)->where('type', WalletLedgerEntryType::FeeDebit)->count())->toBe(1);
    expect(ProviderWebhookEvent::query()->where('reference', $payment->reference)->count())->toBe(1);

    $finished = app(FinishBookingWork::class)->handle(
        app(StartBookingWork::class)->handle($booking, $context['owner']),
        $context['owner'],
    );
    $settled = app(ConfirmBookingCompletion::class)->handle($finished, $context['customer']);
    $wallet->refresh();

    expect($settled->status)->toBe(BookingStatus::Settled);
    expect($settled->wallet_released_at)->not->toBeNull();
    expect($wallet->pending_balance)->toBe(0);
    expect($wallet->available_balance)->toBe(2202500);
    expect(WalletLedgerEntry::query()->where('source_id', $booking->id)->where('type', WalletLedgerEntryType::SettlementDebit)->count())->toBe(1);
    expect(WalletLedgerEntry::query()->where('source_id', $booking->id)->where('type', WalletLedgerEntryType::SettlementCredit)->count())->toBe(1);

    $this
        ->actingAs($context['customer'])
        ->get(route('customer.bookings.show', ['booking' => $settled]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/BookingShow')
            ->where('booking.status', BookingStatus::Settled->value)
            ->where('booking.canReview', true)
            ->where('booking.payment.netAmountDisplay', '22,025.00'));

    $review = app(SubmitVerifiedReview::class)->handle($settled, $context['customer'], 5, 'Great work.');

    expect($review->booking()->firstOrFail()->status)->toBe(BookingStatus::Reviewed);
});

test('invalid booking payment webhook amounts fail without corrupting escrow or ledger state', function (): void {
    /** @var TestCase $this */
    $context = phaseNineContext();
    $booking = phaseNineAcceptedBooking($context);
    phaseNineFakePaystack();
    $payment = app(InitializeBookingPayment::class)->handle(
        booking: $booking,
        callbackUrl: route('customer.bookings.show', ['booking' => $booking]),
    );
    $signed = phaseNineSignedPayload('charge.success', [
        'amount' => $payment->amount + 100,
        'currency' => 'NGN',
        'id' => 9902,
        'reference' => $payment->reference,
        'status' => 'success',
    ]);

    phaseNineWebhook($this, $signed['payload'], $signed['signature'])->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    expect($booking->refresh()->status)->toBe(BookingStatus::Accepted);
    expect(ProviderWebhookEvent::query()->where('reference', $payment->reference)->firstOrFail()->status)
        ->toBe(ProviderWebhookEventStatus::Failed);
    expect(WalletLedgerEntry::query()->where('source_id', $booking->id)->count())->toBe(0);
});

test('failed booking payment webhooks mark payment failed without escrowing funds', function (): void {
    /** @var TestCase $this */
    $context = phaseNineContext();
    $booking = phaseNineAcceptedBooking($context);
    phaseNineFakePaystack();
    $payment = app(InitializeBookingPayment::class)->handle(
        booking: $booking,
        callbackUrl: route('customer.bookings.show', ['booking' => $booking]),
    );
    $signed = phaseNineSignedPayload('charge.failed', [
        'gateway_response' => 'Declined',
        'id' => 9903,
        'reference' => $payment->reference,
        'status' => 'failed',
    ]);

    phaseNineWebhook($this, $signed['payload'], $signed['signature'])->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
    expect($payment->failure_reason)->toBe('Declined');
    expect($booking->refresh()->status)->toBe(BookingStatus::Accepted);
    expect($context['profile']->wallet()->exists())->toBeFalse();
});

test('booking refunds post adjustment and refund ledger entries without mutating history', function (): void {
    /** @var TestCase $this */
    $context = phaseNineContext();
    $booking = phaseNineAcceptedBooking($context);
    phaseNineFakePaystack();
    $payment = app(InitializeBookingPayment::class)->handle(
        booking: $booking,
        callbackUrl: route('customer.bookings.show', ['booking' => $booking]),
    );
    $signed = phaseNineSignedPayload('charge.success', [
        'amount' => (string) $payment->amount,
        'currency' => 'NGN',
        'id' => 9904,
        'reference' => $payment->reference,
        'status' => 'success',
    ]);

    phaseNineWebhook($this, $signed['payload'], $signed['signature'])->assertOk();

    $refunded = app(RefundBookingPayment::class)->handle($booking, 'Customer cancelled before work started.');
    $wallet = $context['profile']->wallet()->firstOrFail();

    expect($refunded->status)->toBe(BookingStatus::Refunded);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
    expect($wallet->refresh()->pending_balance)->toBe(0);
    expect($wallet->available_balance)->toBe(0);
    expect(WalletLedgerEntry::query()->where('immutable_reference', "booking-{$booking->id}-refund-adjustment")->firstOrFail()->amount)
        ->toBe(297500);
    expect(WalletLedgerEntry::query()->where('immutable_reference', "booking-{$booking->id}-refund")->firstOrFail()->amount)
        ->toBe(2500000);

    app(RefundBookingPayment::class)->handle($booking, 'Duplicate retry.');

    expect(WalletLedgerEntry::query()->where('immutable_reference', "booking-{$booking->id}-refund")->count())->toBe(1);
});

test('phase nine guard rails reject invalid payment settlement refund and review transitions', function (): void {
    /** @var TestCase $this */
    $context = phaseNineContext();
    $calculator = app(CalculateBookingSettlement::class);

    expect(fn () => $calculator->handle(0))
        ->toThrow(InvalidArgumentException::class, 'Booking payment amount must be greater than zero.');

    config()->set('lartisan.booking_payments.commission_basis_points', 9000);
    config()->set('lartisan.booking_payments.provider_fee_basis_points', 1000);
    config()->set('lartisan.booking_payments.provider_fee_flat_amount', 1);
    expect(fn () => $calculator->handle(100))
        ->toThrow(InvalidArgumentException::class, 'Booking payment fees exceed the gross payment amount.');
    config()->set('lartisan.booking_payments.commission_basis_points', 1000);
    config()->set('lartisan.booking_payments.provider_fee_basis_points', 150);
    config()->set('lartisan.booking_payments.provider_fee_flat_amount', 10000);

    $requested = phaseNineAcceptedBooking($context);
    $requested->forceFill(['status' => BookingStatus::Requested])->save();
    expect(fn () => app(InitializeBookingPayment::class)->handle($requested, 'https://lartisan.test/callback'))
        ->toThrow(InvalidArgumentException::class, 'Only accepted bookings can be paid.');

    $noAmount = phaseNineAcceptedBooking($context);
    $noAmount->forceFill(['quoted_amount' => null])->save();
    expect(fn () => app(InitializeBookingPayment::class)->handle($noAmount, 'https://lartisan.test/callback'))
        ->toThrow(InvalidArgumentException::class, 'Booking has no payable amount.');

    $providerFailure = phaseNineAcceptedBooking($context);
    Http::preventStrayRequests();
    Http::fake(['https://api.paystack.co/*' => Http::failedConnection()]);
    expect(fn () => app(InitializeBookingPayment::class)->handle($providerFailure, 'https://lartisan.test/callback'))
        ->toThrow(Exception::class);
    expect($providerFailure->payments()->firstOrFail()->status)->toBe(PaymentStatus::Failed);

    $finishedWithoutPayment = Booking::factory()->finished()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    expect(fn () => app(ConfirmBookingCompletion::class)->handle($finishedWithoutPayment, $context['customer']))
        ->toThrow(InvalidArgumentException::class, 'Only paid bookings can be confirmed.');

    $subscriptionPayment = Payment::factory()->successful()->create();
    expect(fn () => app(EscrowBookingPayment::class)->handle($subscriptionPayment))
        ->toThrow(InvalidArgumentException::class, 'Only booking payments can be escrowed.');

    $pendingBooking = phaseNineAcceptedBooking($context);
    $pendingPayment = Payment::factory()->booking($pendingBooking)->create();
    expect(fn () => app(EscrowBookingPayment::class)->handle($pendingPayment))
        ->toThrow(InvalidArgumentException::class, 'Only successful booking payments can be escrowed.');

    $invalidEscrowBooking = Booking::factory()->finished()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    $invalidEscrowPayment = Payment::factory()->booking($invalidEscrowBooking)->successful()->create();
    expect(fn () => app(EscrowBookingPayment::class)->handle($invalidEscrowPayment))
        ->toThrow(InvalidArgumentException::class, 'Booking escrow ledger is missing.');

    $rejectedEscrowBooking = phaseNineAcceptedBooking($context);
    $rejectedEscrowBooking->forceFill(['status' => BookingStatus::Rejected])->save();
    $rejectedEscrowPayment = Payment::factory()->booking($rejectedEscrowBooking)->successful()->create();
    expect(fn () => app(EscrowBookingPayment::class)->handle($rejectedEscrowPayment))
        ->toThrow(InvalidArgumentException::class, 'Only accepted bookings can be escrowed.');

    $paidBooking = phaseNineAcceptedBooking($context);
    $paidBooking->forceFill(['status' => BookingStatus::Paid])->save();
    $paidPayment = Payment::factory()->booking($paidBooking)->successful()->create();
    $escrowed = app(EscrowBookingPayment::class)->handle($paidPayment);
    expect(app(EscrowBookingPayment::class)->handle($paidPayment)->is($escrowed))->toBeTrue();

    $existingGrossBooking = phaseNineAcceptedBooking($context);
    $existingGrossWallet = app(EnsureWallet::class)->handle($context['profile']);
    $existingGrossWallet->forceFill(['pending_balance' => 2500000])->save();
    WalletLedgerEntry::factory()->create([
        'amount' => 2500000,
        'immutable_reference' => "booking-{$existingGrossBooking->id}-gross-escrow",
        'pending_balance_after' => 2500000,
        'source_id' => $existingGrossBooking->id,
        'source_type' => $existingGrossBooking->getMorphClass(),
        'type' => WalletLedgerEntryType::BookingCredit,
        'wallet_id' => $existingGrossWallet->id,
    ]);
    app(EscrowBookingPayment::class)->handle(Payment::factory()->booking($existingGrossBooking)->successful()->create());
    expect(WalletLedgerEntry::query()->where('immutable_reference', "booking-{$existingGrossBooking->id}-gross-escrow")->count())->toBe(1);

    $trackerToken = 'phase-nine-visible-payment';
    $escrowed->forceFill(['secure_token_hash' => hash('sha256', $trackerToken)])->save();
    $this
        ->get(route('booking-tracker.show', ['trackerCode' => $escrowed->tracker_code, 'token' => $trackerToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Tracker')
            ->where('booking.payment.amountDisplay', '25,000.00'));

    $confirmedWithNoNet = Booking::factory()->confirmed()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    Payment::factory()->booking($confirmedWithNoNet)->successful()->create(['net_amount' => null]);
    expect(fn () => app(ReleaseWalletBalance::class)->handle($confirmedWithNoNet))
        ->toThrow(InvalidArgumentException::class, 'Booking has no releasable amount.');

    $cancelledWithSettlement = Booking::factory()->create([
        'artisan_profile_id' => $context['profile']->id,
        'status' => BookingStatus::Cancelled,
    ]);
    WalletLedgerEntry::factory()->create([
        'immutable_reference' => 'phase-nine-cancelled-settlement',
        'source_id' => $cancelledWithSettlement->id,
        'source_type' => $cancelledWithSettlement->getMorphClass(),
        'type' => WalletLedgerEntryType::SettlementCredit,
    ]);
    expect(fn () => app(ReleaseWalletBalance::class)->handle($cancelledWithSettlement))
        ->toThrow(InvalidArgumentException::class, 'Only confirmed bookings can release wallet balance.');

    expect(fn () => app(ActivateSubscription::class)->handle($paidPayment))
        ->toThrow(InvalidArgumentException::class, 'Only subscription payments can activate a subscription.');

    $settled = app(ConfirmBookingCompletion::class)->handle(
        app(FinishBookingWork::class)->handle(
            app(StartBookingWork::class)->handle($escrowed, $context['owner']),
            $context['owner'],
        ),
        $context['customer'],
    );
    expect(fn () => app(RefundBookingPayment::class)->handle($settled))
        ->toThrow(InvalidArgumentException::class, 'Settled bookings require an adjustment instead of a direct refund.');

    $refundWithoutPayment = phaseNineAcceptedBooking($context);
    expect(fn () => app(RefundBookingPayment::class)->handle($refundWithoutPayment))
        ->toThrow(InvalidArgumentException::class, 'Booking has no successful payment to refund.');

    $existingAdjustmentBooking = phaseNineAcceptedBooking($context);
    $existingAdjustmentPayment = Payment::factory()->booking($existingAdjustmentBooking)->successful()->create();
    app(EscrowBookingPayment::class)->handle($existingAdjustmentPayment);
    $existingAdjustmentWallet = $context['profile']->wallet()->firstOrFail();
    $existingAdjustmentWallet->forceFill(['pending_balance' => $existingAdjustmentPayment->amount])->save();
    WalletLedgerEntry::factory()->create([
        'amount' => 297500,
        'immutable_reference' => "booking-{$existingAdjustmentBooking->id}-refund-adjustment",
        'source_id' => $existingAdjustmentBooking->id,
        'source_type' => $existingAdjustmentBooking->getMorphClass(),
        'type' => WalletLedgerEntryType::AdjustmentCredit,
        'wallet_id' => $existingAdjustmentWallet->id,
    ]);
    app(RefundBookingPayment::class)->handle($existingAdjustmentBooking);
    expect(WalletLedgerEntry::query()->where('immutable_reference', "booking-{$existingAdjustmentBooking->id}-refund-adjustment")->count())->toBe(1);

    $settledWithoutCredit = Booking::factory()->settled()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    expect(fn () => app(SubmitVerifiedReview::class)->handle($settledWithoutCredit, $context['customer'], 5))
        ->toThrow(InvalidArgumentException::class, 'Only bookings with released settlement credit can be reviewed.');
});
