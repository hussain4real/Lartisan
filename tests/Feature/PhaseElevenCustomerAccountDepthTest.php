<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Bookings\AcceptBooking;
use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\EnsureBookingOtpVerified;
use App\Actions\Bookings\FinishBookingWork;
use App\Actions\Bookings\StartBookingWork;
use App\Actions\Bookings\UpgradeGuestBookingAccount;
use App\Actions\Disputes\OpenGuestDispute;
use App\Actions\Identity\IssueOtp;
use App\Actions\Payments\EscrowBookingPayment;
use App\Actions\Reviews\SubmitVerifiedReview;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\BookingStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\OtpPurpose;
use App\Enums\PreferredChannel;
use App\Enums\ReviewStatus;
use App\Enums\SupportCasePriority;
use App\Http\Requests\BookingTracker\StoreGuestDisputeRequest;
use App\Http\Requests\BookingTracker\StoreGuestReviewRequest;
use App\Models\Address;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\CustomerFavorite;
use App\Models\CustomerProfile;
use App\Models\Dispute;
use App\Models\LocalGovernment;
use App\Models\OtpRecord;
use App\Models\Payment;
use App\Models\Review;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportCase;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    $this->seed(PlatformAccessSeeder::class);
    $this->seed(GeographySeeder::class);
});

/**
 * @return array{state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseElevenGeography(): array
{
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
    $localGovernment = $territory->localGovernment()->firstOrFail();

    return [
        'state' => $localGovernment->state()->firstOrFail(),
        'localGovernment' => $localGovernment,
        'territory' => $territory,
    ];
}

/**
 * @return array{owner: User, customer: User, profile: ArtisanProfile, team: Team, service: ArtisanService, state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseElevenContext(): array
{
    $geography = phaseElevenGeography();
    $suffix = Str::lower(Str::random(8));

    $customerPhone = '803555'.fake()->unique()->numerify('####');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase Eleven Repairs '.$suffix,
        'slug' => 'phase-eleven-repairs-'.$suffix,
    ]);
    $owner = User::factory()->create(['name' => 'Phase Eleven Artisan']);
    $customer = User::factory()->create([
        'name' => 'Phase Eleven Customer',
        'phone_country_code' => '+234',
        'phone_number' => $customerPhone,
        'phone_e164' => '+234'.$customerPhone,
        'phone_verified_at' => now(),
    ]);
    $profile = app(CreateArtisanBusinessProfile::class)->handle($owner, 'Phase Eleven Electrical');
    $profile->forceFill([
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'country_id' => $geography['state']->country_id,
        'is_public' => true,
        'local_government_id' => $geography['localGovernment']->id,
        'state_id' => $geography['state']->id,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'territory_id' => $geography['territory']->id,
        'verification_status' => ArtisanVerificationStatus::Approved,
    ])->save();
    $service = ArtisanService::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'currency_code' => 'NGN',
        'service_category_id' => $category->id,
        'starting_price' => '25000.00',
        'title' => 'Phase Eleven Repair',
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
        'state' => $geography['state'],
        'localGovernment' => $geography['localGovernment'],
        'territory' => $geography['territory'],
    ];
}

/**
 * @param  array{owner: User, customer: User, profile: ArtisanProfile, team: Team, service: ArtisanService, state: State, localGovernment: LocalGovernment, territory: Territory}  $context
 * @param  array<string, mixed>  $address
 * @return array{booking: Booking, token: string}
 */
function phaseElevenGuestBooking(array $context, array $address = []): array
{
    $guestPhone = '+234803777'.fake()->unique()->numerify('####');

    $created = app(CreateBooking::class)->handle(
        profile: $context['profile'],
        service: $context['service'],
        customer: null,
        customerName: 'Phase Eleven Guest',
        customerPhone: $guestPhone,
        customerEmail: 'phase-eleven-guest@example.test',
        addressSnapshot: [
            'line_1' => '44 Guest Crescent',
            'country_id' => $context['state']->country_id,
            'state_id' => $context['state']->id,
            'local_government_id' => $context['localGovernment']->id,
            'territory_id' => $context['territory']->id,
            ...$address,
        ],
        scheduledAt: now()->addDay(),
        description: 'Guest booking for phase eleven.',
    );

    return ['booking' => $created->booking, 'token' => $created->trackerToken];
}

/**
 * @param  array{owner: User, customer: User, profile: ArtisanProfile, team: Team, service: ArtisanService, state: State, localGovernment: LocalGovernment, territory: Territory}  $context
 * @return array{booking: Booking, token: string}
 */
function phaseElevenSettledGuestBooking(array $context): array
{
    $tracked = phaseElevenGuestBooking($context);
    $booking = app(AcceptBooking::class)->handle($tracked['booking'], $context['owner']);
    $payment = Payment::factory()->booking($booking)->successful()->create([
        'amount' => $booking->quoted_amount,
        'currency_code' => $booking->currency_code,
    ]);
    $escrowed = app(EscrowBookingPayment::class)->handle($payment);
    $started = app(StartBookingWork::class)->handle($escrowed, $context['owner']);
    $finished = app(FinishBookingWork::class)->handle($started, $context['owner']);

    return [
        'booking' => app(ConfirmBookingCompletion::class)->handle($finished, trackerToken: $tracked['token']),
        'token' => $tracked['token'],
    ];
}

test('booking OTP and saved addresses are enforced for marketplace booking', function (): void {
    $context = phaseElevenContext();
    $customer = $context['customer'];
    $address = Address::factory()->create([
        'user_id' => $customer->id,
        'label' => 'Workshop',
        'contact_name' => 'Phase Eleven Contact',
        'phone' => $customer->phone_number,
        'country_id' => $context['state']->country_id,
        'state_id' => $context['state']->id,
        'local_government_id' => $context['localGovernment']->id,
        'territory_id' => $context['territory']->id,
        'line_1' => '88 Saved Address Road',
        'is_default' => true,
    ]);
    CustomerProfile::factory()->create([
        'user_id' => $customer->id,
        'default_address_id' => $address->id,
        'preferences' => [
            'preferred_channel' => PreferredChannel::Sms->value,
            'schedule_window' => 'morning',
            'default_notes' => 'Use my saved booking notes.',
        ],
    ]);

    $this
        ->actingAs($customer)
        ->get(route('marketplace.bookings.create', ['artisanProfile' => $context['profile']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Book')
            ->where('savedAddresses.0.id', $address->id)
            ->where('customerDefaults.defaultNotes', 'Use my saved booking notes.')
            ->where('requiresOtp', false));

    $this
        ->actingAs($customer)
        ->post(route('marketplace.bookings.store', ['artisanProfile' => $context['profile']]), [
            'artisan_service_id' => $context['service']->id,
            'address_id' => $address->id,
            'customer_name' => 'Phase Eleven Customer',
            'phone_country_code' => '+234',
            'customer_phone' => $customer->phone_number,
            'customer_email' => 'phase-eleven@example.test',
        ])
        ->assertRedirect();

    $booking = Booking::query()->where('customer_email', 'phase-eleven@example.test')->firstOrFail();
    expect($booking->customer_id)->toBe($customer->id);
    expect($booking->customer_phone)->toBe($customer->phone_e164);
    expect($booking->address_snapshot['address_id'])->toBe($address->id);
    expect($booking->address_snapshot['line_1'])->toBe('88 Saved Address Road');

    $otherAddress = Address::factory()->create();
    $this
        ->actingAs($customer)
        ->post(route('marketplace.bookings.store', ['artisanProfile' => $context['profile']]), [
            'artisan_service_id' => $context['service']->id,
            'address_id' => $otherAddress->id,
            'customer_name' => 'Phase Eleven Customer',
            'phone_country_code' => '+234',
            'customer_phone' => $customer->phone_number,
        ])
        ->assertSessionHasErrors('address_id');

    auth()->logout();

    $this
        ->post(route('marketplace.bookings.store', ['artisanProfile' => $context['profile']]), [
            'artisan_service_id' => $context['service']->id,
            'customer_name' => 'Guest Customer',
            'phone_country_code' => '+234',
            'customer_phone' => '8037000000',
            'line_1' => '12 Guest Street',
            'state_id' => $context['state']->id,
            'local_government_id' => $context['localGovernment']->id,
        ])
        ->assertSessionHasErrors('otp_code');

    $this
        ->post(route('marketplace.bookings.store', ['artisanProfile' => $context['profile']]), [
            'artisan_service_id' => $context['service']->id,
            'address_id' => $address->id,
            'customer_name' => 'Guest Customer',
            'phone_country_code' => '+234',
            'customer_phone' => '8037000000',
        ])
        ->assertSessionHasErrors('address_id');

    $this
        ->post(route('marketplace.booking-otp.issue'), [
            'phone_country_code' => '+234',
            'customer_phone' => '8037000000',
        ])
        ->assertRedirect();

    expect(OtpRecord::query()->where('purpose', OtpPurpose::BookingGuest)->where('phone_e164', '+2348037000000')->exists())->toBeTrue();

    $this
        ->actingAs($customer)
        ->post(route('marketplace.booking-otp.issue'), [
            'phone_country_code' => '+234',
            'customer_phone' => $customer->phone_number,
            'preferred_channel' => PreferredChannel::Email->value,
        ])
        ->assertRedirect();

    expect($customer->refresh()->preferred_channel)->toBe(PreferredChannel::Email);
});

test('booking OTP verification covers verified skip missing invalid and success branches', function (): void {
    $customer = User::factory()->create([
        'phone_country_code' => '+234',
        'phone_number' => '8035550000',
        'phone_e164' => '+2348035550000',
        'phone_verified_at' => now(),
    ]);
    $action = app(EnsureBookingOtpVerified::class);

    expect($action->handle($customer, '+234', '8035550000', null)['e164'])->toBe('+2348035550000');
    expect(fn () => $action->handle(null, '+234', '8037000001', null))
        ->toThrow(ValidationException::class);

    app(IssueOtp::class)->handle(null, '+234', '8037000001', OtpPurpose::BookingGuest, '111111');
    expect(fn () => $action->handle(null, '+234', '8037000001', '000000'))
        ->toThrow(ValidationException::class);

    app(IssueOtp::class)->handle(null, '+234', '8037000001', OtpPurpose::BookingGuest, '222222');
    expect($action->handle(null, '+234', '8037000001', '222222')['e164'])->toBe('+2348037000001');
});

test('guest account upgrade attaches the booking and creates customer profile data', function (): void {
    $context = phaseElevenContext();
    $tracked = phaseElevenGuestBooking($context);

    $this
        ->post(route('booking-tracker.account.store', ['trackerCode' => $tracked['booking']->tracker_code]), [
            'token' => 'wrong-token',
            'name' => 'Phase Eleven Guest',
            'email' => 'guest-upgrade@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertForbidden();

    expect(fn () => app(UpgradeGuestBookingAccount::class)->handle(
        booking: $tracked['booking'],
        trackerToken: 'wrong-token',
        name: 'Wrong Token Guest',
        email: 'wrong-token-guest@example.test',
        password: 'password',
    ))->toThrow(AuthorizationException::class);

    $this
        ->post(route('booking-tracker.account.store', ['trackerCode' => $tracked['booking']->tracker_code]), [
            'token' => $tracked['token'],
            'name' => 'Phase Eleven Guest',
            'email' => 'guest-upgrade@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('customer.bookings.show', ['booking' => $tracked['booking']]));

    $user = User::query()->where('email', 'guest-upgrade@example.test')->firstOrFail();
    expect(auth()->id())->toBe($user->id);

    expect($tracked['booking']->refresh()->customer_id)->toBe($user->id);
    expect($user->customerProfile()->firstOrFail()->defaultAddress()->firstOrFail()->line_1)->toBe('44 Guest Crescent');
    expect($user->phone_e164)->toBe($tracked['booking']->customer_phone);
    expect($user->phone_verified_at)->not->toBeNull();

    expect(fn () => app(UpgradeGuestBookingAccount::class)->handle(
        booking: $tracked['booking']->refresh(),
        trackerToken: $tracked['token'],
        name: 'Duplicate',
        email: 'duplicate@example.test',
        password: 'password',
    ))->toThrow(InvalidArgumentException::class, 'This booking is already attached to a customer account.');

    $withoutAddress = phaseElevenGuestBooking($context, [
        'line_1' => 'Address without local government',
        'state_id' => null,
        'local_government_id' => null,
        'territory_id' => null,
    ]);
    $upgraded = app(UpgradeGuestBookingAccount::class)->handle(
        booking: $withoutAddress['booking'],
        trackerToken: $withoutAddress['token'],
        name: 'No Address Guest',
        email: 'no-address-guest@example.test',
        password: 'password',
    );

    expect($upgraded->customerProfile()->firstOrFail()->default_address_id)->toBeNull();

    $withoutCountry = phaseElevenGuestBooking($context, [
        'country_id' => null,
        'state_id' => null,
        'local_government_id' => null,
        'territory_id' => null,
    ]);
    $countryFallbackUser = app(UpgradeGuestBookingAccount::class)->handle(
        booking: $withoutCountry['booking'],
        trackerToken: $withoutCountry['token'],
        name: 'No Country Guest',
        email: 'no-country-guest@example.test',
        password: 'password',
    );

    expect($countryFallbackUser->phone_country_code)->toBe('+234');
});

test('guest tracker review and dispute flows are token limited and persisted', function (): void {
    $context = phaseElevenContext();
    $tracked = phaseElevenSettledGuestBooking($context);
    $booking = $tracked['booking'];

    $this
        ->get(route('booking-tracker.show', [
            'trackerCode' => $booking->tracker_code,
            'token' => $tracked['token'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Tracker')
            ->where('booking.canUpgrade', true)
            ->where('booking.canReview', true)
            ->where('booking.canDispute', true));

    expect(fn () => app(SubmitVerifiedReview::class)->handle($booking, null, 5, trackerToken: 'wrong-token'))
        ->toThrow(AuthorizationException::class);

    $this
        ->post(route('booking-tracker.reviews.store', ['trackerCode' => $booking->tracker_code]), [
            'token' => 'wrong-token',
            'rating' => 5,
        ])
        ->assertForbidden();

    $this
        ->post(route('booking-tracker.reviews.store', ['trackerCode' => $booking->tracker_code]), [
            'token' => $tracked['token'],
            'rating' => 5,
            'comment' => 'Guest verified review.',
        ])
        ->assertRedirect(route('booking-tracker.show', [
            'trackerCode' => $booking->tracker_code,
            'token' => $tracked['token'],
        ]));

    $review = Review::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($review->customer_id)->toBeNull();
    expect($review->status)->toBe(ReviewStatus::Published);
    expect($booking->refresh()->status)->toBe(BookingStatus::Reviewed);

    $singleProofRequest = StoreGuestReviewRequest::create('/', 'POST', [], [], [
        'proof' => UploadedFile::fake()->image('single-proof.jpg'),
    ]);
    expect($singleProofRequest->proof())->toHaveCount(1);

    $wrongReview = Review::factory()->create();
    expect(fn () => app(OpenGuestDispute::class)->handle($booking, $tracked['token'], 'Wrong review', review: $wrongReview))
        ->toThrow(InvalidArgumentException::class, 'The selected review does not belong to this booking.');
    $wrongPayment = Payment::factory()->successful()->create();
    expect(fn () => app(OpenGuestDispute::class)->handle($booking, $tracked['token'], 'Wrong payment', payment: $wrongPayment))
        ->toThrow(InvalidArgumentException::class, 'The selected payment does not belong to this booking.');
    expect(fn () => app(OpenGuestDispute::class)->handle($booking, $tracked['token'], ''))
        ->toThrow(InvalidArgumentException::class, 'A dispute subject is required.');
    expect(fn () => app(OpenGuestDispute::class)->handle($booking, 'wrong-token', 'Wrong token'))
        ->toThrow(AuthorizationException::class);

    $registeredBooking = Booking::factory()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
        'secure_token_hash' => hash('sha256', 'registered-token'),
    ]);
    expect(fn () => app(OpenGuestDispute::class)->handle($registeredBooking, 'registered-token', 'Registered'))
        ->toThrow(AuthorizationException::class);

    $this
        ->post(route('booking-tracker.disputes.store', ['trackerCode' => $booking->tracker_code]), [
            'token' => $tracked['token'],
            'review_id' => $review->id,
            'subject' => 'Guest dispute',
            'description' => 'The final work is contested.',
            'severity' => DisputeSeverity::High->value,
            'evidence' => [UploadedFile::fake()->image('guest-evidence.jpg')],
        ])
        ->assertRedirect(route('booking-tracker.show', [
            'trackerCode' => $booking->tracker_code,
            'token' => $tracked['token'],
        ]));

    $singleEvidenceRequest = StoreGuestDisputeRequest::create('/', 'POST', [], [], [
        'evidence' => UploadedFile::fake()->image('single-evidence.jpg'),
    ]);
    expect($singleEvidenceRequest->evidence())->toHaveCount(1);

    $dispute = Dispute::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($dispute->status)->toBe(DisputeStatus::Open);
    expect($dispute->opened_by_id)->toBeNull();
    expect($dispute->customer_id)->toBeNull();
    expect($dispute->metadata)->toMatchArray(['source' => 'guest_tracker']);
    expect($dispute->getMedia(Dispute::EVIDENCE_COLLECTION))->toHaveCount(1);
    expect($dispute->supportCases()->firstOrFail()->priority)->toBe(SupportCasePriority::High);
    expect($review->refresh()->status)->toBe(ReviewStatus::Disputed);

    foreach ([DisputeSeverity::Low, DisputeSeverity::Medium, DisputeSeverity::Critical] as $severity) {
        $extra = phaseElevenGuestBooking($context);
        app(OpenGuestDispute::class)->handle($extra['booking'], $extra['token'], Str::headline($severity->value), severity: $severity);
    }

    expect(SupportCase::query()->where('priority', SupportCasePriority::Low)->exists())->toBeTrue();
    expect(SupportCase::query()->where('priority', SupportCasePriority::Normal)->exists())->toBeTrue();
    expect(SupportCase::query()->where('priority', SupportCasePriority::Urgent)->exists())->toBeTrue();
});

test('favorites and booking preferences persist for registered customers', function (): void {
    $context = phaseElevenContext();
    $customer = $context['customer'];

    $this
        ->post(route('customer.favorites.store', ['artisanProfile' => $context['profile']]))
        ->assertRedirect(route('login'));

    $this
        ->actingAs($customer)
        ->get(route('marketplace.artisans.show', ['artisanProfile' => $context['profile']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Show')
            ->where('artisan.isFavorite', false));

    $this
        ->actingAs($customer)
        ->post(route('customer.favorites.store', ['artisanProfile' => $context['profile']]))
        ->assertRedirect();
    $this
        ->actingAs($customer)
        ->post(route('customer.favorites.store', ['artisanProfile' => $context['profile']]))
        ->assertRedirect();

    expect(CustomerFavorite::query()->where('user_id', $customer->id)->where('artisan_profile_id', $context['profile']->id)->count())->toBe(1);
    expect($customer->customerFavorites()->firstOrFail()->artisanProfile()->firstOrFail()->is($context['profile']))->toBeTrue();
    expect($context['profile']->customerFavorites()->firstOrFail()->user()->firstOrFail()->is($customer))->toBeTrue();

    $this
        ->actingAs($customer)
        ->get(route('marketplace.artisans.show', ['artisanProfile' => $context['profile']]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('artisan.isFavorite', true));

    $this
        ->actingAs($customer)
        ->delete(route('customer.favorites.destroy', ['artisanProfile' => $context['profile']]))
        ->assertRedirect();

    expect($customer->customerFavorites()->exists())->toBeFalse();

    $hidden = phaseElevenContext();
    $hidden['profile']->forceFill(['is_public' => false])->save();
    actingAs($customer);
    post(route('customer.favorites.store', ['artisanProfile' => $hidden['profile']]))
        ->assertNotFound();

    $this
        ->actingAs($customer)
        ->get(route('customer.preferences.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/Preferences')
            ->where('preferences.preferredChannel', PreferredChannel::Whatsapp->value)
            ->where('preferences.scheduleWindow', 'flexible'));

    $this
        ->actingAs($customer)
        ->patch(route('customer.preferences.update'), [
            'preferred_channel' => PreferredChannel::Sms->value,
            'schedule_window' => 'weekend',
            'default_notes' => 'Please call before arriving.',
        ])
        ->assertRedirect();

    $this
        ->actingAs($customer)
        ->get(route('customer.preferences.show'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('preferences.preferredChannel', PreferredChannel::Sms->value)
            ->where('preferences.scheduleWindow', 'weekend')
            ->where('preferences.defaultNotes', 'Please call before arriving.'));

    $profile = $customer->customerProfile()->firstOrFail();
    expect($profile->preferences)->toMatchArray([
        'preferred_channel' => PreferredChannel::Sms->value,
        'schedule_window' => 'weekend',
        'default_notes' => 'Please call before arriving.',
    ]);
    expect($customer->refresh()->preferred_channel)->toBe(PreferredChannel::Sms);

    $this
        ->actingAs($customer)
        ->get(route('marketplace.bookings.create', ['artisanProfile' => $context['profile']]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('customerDefaults.defaultNotes', 'Please call before arriving.')
            ->where('customerDefaults.scheduleWindow', 'weekend'));
});
