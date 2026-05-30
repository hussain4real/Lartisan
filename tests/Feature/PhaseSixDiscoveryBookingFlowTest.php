<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Bookings\AcceptBooking;
use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\FinishBookingWork;
use App\Actions\Bookings\RejectBooking;
use App\Actions\Bookings\ReleaseWalletBalance;
use App\Actions\Bookings\SearchArtisans;
use App\Actions\Bookings\StartBookingWork;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\BookingStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Support\Bookings\CreatedBooking;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PlatformAccessSeeder::class);
    $this->seed(GeographySeeder::class);
});

/**
 * @return array{country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseSixGeography(): array
{
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
    $localGovernment = $territory->localGovernment()->firstOrFail();
    $state = $localGovernment->state()->firstOrFail();

    return [
        'country' => $state->country()->firstOrFail(),
        'state' => $state,
        'localGovernment' => $localGovernment,
        'territory' => $territory,
    ];
}

/**
 * @return array{owner: User, customer: User, profile: ArtisanProfile, team: Team, category: ServiceCategory, service: ArtisanService, country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseSixArtisanContext(
    string $businessName = 'Phase Six Electrical',
    ?ServiceCategory $category = null,
    ?Territory $territory = null,
    ArtisanAvailabilityStatus $availabilityStatus = ArtisanAvailabilityStatus::Online,
): array {
    $geography = phaseSixGeography();
    $territory ??= $geography['territory'];
    $localGovernment = $territory->localGovernment()->firstOrFail();
    $state = $localGovernment->state()->firstOrFail();
    $country = $state->country()->firstOrFail();
    $category ??= ServiceCategory::factory()->create([
        'name' => $businessName.' Category',
        'slug' => Str::slug($businessName).'-category',
    ]);
    $owner = User::factory()->create(['name' => $businessName.' Owner']);
    $customer = User::factory()->create(['name' => 'Phase Six Customer']);
    $profile = app(CreateArtisanBusinessProfile::class)->handle($owner, $businessName);
    $profile->forceFill([
        'verification_status' => ArtisanVerificationStatus::Approved,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'availability_status' => $availabilityStatus,
        'is_public' => true,
        'public_summary' => $businessName.' handles reliable field work.',
        'years_experience' => 9,
        'service_radius_km' => 30,
        'public_phone' => '+2348031112222',
        'public_email' => 'phase-six@example.test',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $localGovernment->id,
        'territory_id' => $territory->id,
        'approved_at' => now(),
    ])->save();
    $service = ArtisanService::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'title' => $businessName.' Repair',
        'description' => 'Electrical emergency support.',
        'starting_price' => '25000.00',
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
        'category' => $category,
        'service' => $service,
        'country' => $country,
        'state' => $state,
        'localGovernment' => $localGovernment,
        'territory' => $territory,
    ];
}

/**
 * @param  array{owner: User, customer: User, profile: ArtisanProfile, team: Team, category: ServiceCategory, service: ArtisanService, country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}  $context
 * @param  array{customer?: User|null, profile?: ArtisanProfile, service?: ArtisanService, country?: Country, state?: State, localGovernment?: LocalGovernment, territory?: Territory}  $overrides
 */
function phaseSixCreateBooking(array $context, array $overrides = []): CreatedBooking
{
    return app(CreateBooking::class)->handle(
        profile: $overrides['profile'] ?? $context['profile'],
        service: $overrides['service'] ?? $context['service'],
        customer: $overrides['customer'] ?? $context['customer'],
        customerName: 'Phase Six Customer',
        customerPhone: '+2348030001111',
        customerEmail: 'customer@example.test',
        addressSnapshot: [
            'line_1' => '15 Wuse Market Road',
            'line_2' => 'Suite 4',
            'landmark' => 'Beside the plaza',
            'country_id' => ($overrides['country'] ?? $context['country'])->id,
            'state_id' => ($overrides['state'] ?? $context['state'])->id,
            'local_government_id' => ($overrides['localGovernment'] ?? $context['localGovernment'])->id,
            'territory_id' => ($overrides['territory'] ?? $context['territory'])->id,
        ],
        scheduledAt: now()->addDay(),
        description: 'Please inspect the main panel.',
    );
}

test('phase six models expose booking media status geography and wallet relationships', function () {
    Storage::fake('local');
    $context = phaseSixArtisanContext();
    $createdBooking = app(CreateBooking::class)->handle(
        profile: $context['profile'],
        service: $context['service'],
        customer: $context['customer'],
        customerName: ' Registered Customer ',
        customerPhone: ' +2348034567890 ',
        customerEmail: 'registered@example.test',
        addressSnapshot: [
            'line_1' => '20 Aminu Kano Crescent',
            'line_2' => null,
            'landmark' => 'Near the bank',
            'country_id' => $context['country']->id,
            'state_id' => $context['state']->id,
            'local_government_id' => $context['localGovernment']->id,
            'territory_id' => $context['territory']->id,
        ],
        scheduledAt: now()->addDay(),
        description: ' Bring a tester. ',
        attachments: [UploadedFile::fake()->image('fault.jpg')],
    );
    $booking = $createdBooking->booking;
    $history = $booking->statusHistories()->firstOrFail();

    expect(BookingStatus::cases())->toHaveCount(7);
    expect($createdBooking->trackerUrl())->toContain($booking->tracker_code);
    expect($booking->status)->toBe(BookingStatus::Requested);
    expect($booking->quoted_amount)->toBe(2500000);
    expect($booking->customer()->firstOrFail()->is($context['customer']))->toBeTrue();
    expect($booking->artisanProfile()->firstOrFail()->is($context['profile']))->toBeTrue();
    expect($booking->artisanService()->firstOrFail()->is($context['service']))->toBeTrue();
    expect($booking->serviceCategory()->firstOrFail()->is($context['category']))->toBeTrue();
    expect($booking->country()->firstOrFail()->is($context['country']))->toBeTrue();
    expect($booking->state()->firstOrFail()->is($context['state']))->toBeTrue();
    expect($booking->localGovernment()->firstOrFail()->is($context['localGovernment']))->toBeTrue();
    expect($booking->territory()->firstOrFail()->is($context['territory']))->toBeTrue();
    expect($booking->getMedia(Booking::MEDIA_COLLECTION))->toHaveCount(1);
    expect($context['profile']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['service']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['category']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['customer']->customerBookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['country']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['state']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['localGovernment']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($context['territory']->bookings()->firstOrFail()->is($booking))->toBeTrue();
    expect($history->booking()->firstOrFail()->is($booking))->toBeTrue();
    expect($history->actor()->firstOrFail()->is($context['customer']))->toBeTrue();
    expect($history->to_status)->toBe(BookingStatus::Requested);
    expect($context['customer']->bookingStatusHistories()->firstOrFail()->is($history))->toBeTrue();
});

test('search finds only verified subscribed public artisans and ranks by category and geography', function () {
    $category = ServiceCategory::factory()->create(['name' => 'Electrical', 'slug' => 'electrical-search']);
    $primary = phaseSixArtisanContext('Alpha Electrical', $category);
    $otherLocalGovernment = LocalGovernment::factory()->create(['state_id' => $primary['state']->id, 'name' => 'Garki LGA']);
    $nearTerritory = Territory::factory()->create(['local_government_id' => $otherLocalGovernment->id, 'name' => 'Garki Area']);
    $busy = phaseSixArtisanContext('Beta Electrical', $category, $nearTerritory, ArtisanAvailabilityStatus::Busy);
    $offline = phaseSixArtisanContext('Gamma Electrical', $category, $nearTerritory, ArtisanAvailabilityStatus::Offline);
    $vacation = phaseSixArtisanContext('Vacation Electrical', $category, $nearTerritory, ArtisanAvailabilityStatus::Vacation);
    $trial = phaseSixArtisanContext('Trial Electrical', $category, $nearTerritory);
    $trial['profile']->forceFill([
        'subscription_status' => ArtisanSubscriptionStatus::Trial,
        'is_public' => false,
    ])->save();

    $results = app(SearchArtisans::class)->handle(
        query: 'Electrical',
        category: $category,
        state: $primary['state'],
        localGovernment: $primary['localGovernment'],
        territory: $primary['territory'],
    );
    $unfiltered = app(SearchArtisans::class)->handle(limit: 2);

    expect($results->pluck('id')->all())->toContain($primary['profile']->id, $busy['profile']->id, $offline['profile']->id);
    expect($results->first()?->is($primary['profile']))->toBeTrue();
    expect($results->pluck('id')->all())->not->toContain($vacation['profile']->id, $trial['profile']->id);
    expect($unfiltered)->toHaveCount(2);
});

test('guest and registered customers can create bookings and use secure tracker screens', function () {
    Storage::fake('local');
    $context = phaseSixArtisanContext();

    /** @var TestResponse<Response> $guestResponse */
    $guestResponse = $this->post(route('marketplace.bookings.store', ['artisanProfile' => $context['profile']]), [
        'artisan_service_id' => $context['service']->id,
        'customer_name' => 'Guest Customer',
        'customer_phone' => '+2348039990000',
        'customer_email' => 'guest@example.test',
        'scheduled_at' => now()->addDay()->toDateString(),
        'line_1' => '12 Guest Street',
        'line_2' => 'Flat 1',
        'landmark' => 'Blue gate',
        'country_id' => $context['country']->id,
        'state_id' => $context['state']->id,
        'local_government_id' => $context['localGovernment']->id,
        'territory_id' => $context['territory']->id,
        'description' => 'The socket sparks.',
        'attachments' => [
            UploadedFile::fake()->image('socket.jpg'),
        ],
    ]);
    $guestBooking = Booking::query()->where('customer_email', 'guest@example.test')->firstOrFail();
    $trackerUrl = $guestResponse->headers->get('Location');
    assert(is_string($trackerUrl));
    $trackerQuery = (string) parse_url($trackerUrl, PHP_URL_QUERY);
    parse_str($trackerQuery, $query);
    $trackerToken = is_string($query['token'] ?? null) ? $query['token'] : '';

    $guestResponse->assertRedirect();
    expect($guestBooking->customer_id)->toBeNull();
    expect($guestBooking->getMedia(Booking::MEDIA_COLLECTION))->toHaveCount(1);

    $this->get($trackerUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Tracker')
            ->where('booking.trackerCode', $guestBooking->tracker_code)
            ->where('booking.status', BookingStatus::Requested->value));

    $this->get(route('booking-tracker.show', ['trackerCode' => $guestBooking->tracker_code, 'token' => 'bad-token']))
        ->assertForbidden();

    $registeredBooking = phaseSixCreateBooking($context);

    $this->actingAs($context['customer'])
        ->get(route('customer.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/Bookings')
            ->where('bookings.0.id', $registeredBooking->booking->id));

    $this->actingAs($context['customer'])
        ->get(route('customer.bookings.show', ['booking' => $registeredBooking->booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/BookingShow')
            ->where('booking.trackerCode', $registeredBooking->booking->tracker_code));

    $finishedGuest = app(FinishBookingWork::class)->handle(
        app(StartBookingWork::class)->handle(
            app(AcceptBooking::class)->handle($guestBooking, $context['owner']),
            $context['owner'],
        ),
        $context['owner'],
    );

    $this->post(route('booking-tracker.confirm', [
        'trackerCode' => $finishedGuest->tracker_code,
    ]), ['token' => $trackerToken])
        ->assertRedirect(route('booking-tracker.show', [
            'trackerCode' => $finishedGuest->tracker_code,
            'token' => $trackerToken,
        ]));
});

test('booking lifecycle actions enforce status authorization and release wallet balance once', function () {
    $context = phaseSixArtisanContext();
    $inactive = phaseSixArtisanContext('Inactive Electrical');
    $inactive['profile']->forceFill(['is_public' => false])->save();
    $otherService = phaseSixArtisanContext('Other Electrical')['service'];

    expect(fn () => phaseSixCreateBooking($context, ['profile' => $inactive['profile'], 'service' => $inactive['service']]))
        ->toThrow(InvalidArgumentException::class, 'This artisan is not available for bookings.');
    expect(fn () => phaseSixCreateBooking($context, ['service' => $otherService]))
        ->toThrow(InvalidArgumentException::class, 'The selected service is not available for this artisan.');

    $createdBooking = phaseSixCreateBooking($context);
    $booking = $createdBooking->booking;
    $stranger = User::factory()->create();

    expect(fn () => app(AcceptBooking::class)->handle($booking, $stranger))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(StartBookingWork::class)->handle($booking, $context['owner']))
        ->toThrow(InvalidArgumentException::class, 'Only accepted bookings can be started.');

    $accepted = app(AcceptBooking::class)->handle($booking, $context['owner']);
    expect($accepted->status)->toBe(BookingStatus::Accepted);
    expect(fn () => app(AcceptBooking::class)->handle($accepted, $context['owner']))
        ->toThrow(InvalidArgumentException::class, 'Only requested bookings can be accepted.');
    expect(fn () => app(RejectBooking::class)->handle($accepted, $context['owner']))
        ->toThrow(InvalidArgumentException::class, 'Only requested bookings can be rejected.');
    expect(fn () => app(FinishBookingWork::class)->handle($accepted, $context['owner']))
        ->toThrow(InvalidArgumentException::class, 'Only in-progress bookings can be finished.');

    $inProgress = app(StartBookingWork::class)->handle($accepted, $context['owner']);
    $finished = app(FinishBookingWork::class)->handle($inProgress, $context['owner']);
    $unconfirmed = phaseSixCreateBooking($context)->booking;
    expect(fn () => app(ConfirmBookingCompletion::class)->handle($unconfirmed, $context['customer']))
        ->toThrow(InvalidArgumentException::class, 'Only finished bookings can be confirmed.');
    expect(fn () => app(ConfirmBookingCompletion::class)->handle($finished))
        ->toThrow(AuthorizationException::class);

    $confirmed = app(ConfirmBookingCompletion::class)->handle($finished, $context['customer']);
    $ledgerEntry = WalletLedgerEntry::query()
        ->where('source_type', $confirmed->getMorphClass())
        ->where('source_id', $confirmed->id)
        ->firstOrFail();
    $secondRelease = app(ReleaseWalletBalance::class)->handle($confirmed);

    expect($confirmed->status)->toBe(BookingStatus::Confirmed);
    expect($confirmed->wallet_released_at)->not->toBeNull();
    expect($ledgerEntry->type)->toBe(WalletLedgerEntryType::BookingCredit);
    expect($ledgerEntry->amount)->toBe(2500000);
    expect($ledgerEntry->source()->firstOrFail()->is($confirmed))->toBeTrue();
    expect($ledgerEntry->wallet()->firstOrFail()->available_balance)->toBe(2500000);
    expect($secondRelease->is($ledgerEntry))->toBeTrue();
    expect(BookingStatusHistory::query()->where('booking_id', $confirmed->id)->count())->toBe(5);

    $noAmount = Booking::factory()->create([
        'artisan_profile_id' => $context['profile']->id,
        'status' => BookingStatus::Confirmed,
        'quoted_amount' => null,
    ]);
    $unreleased = phaseSixCreateBooking($context)->booking;
    expect(fn () => app(ReleaseWalletBalance::class)->handle($unreleased))
        ->toThrow(InvalidArgumentException::class, 'Only confirmed bookings can release wallet balance.');
    expect(fn () => app(ReleaseWalletBalance::class)->handle($noAmount))
        ->toThrow(InvalidArgumentException::class, 'Booking has no releasable amount.');

    $rejectable = phaseSixCreateBooking($context)->booking;
    expect(app(RejectBooking::class)->handle($rejectable, $context['owner'])->status)->toBe(BookingStatus::Rejected);
});

test('phase six inertia contracts and artisan booking routes are wired', function () {
    Storage::fake('public');
    $context = phaseSixArtisanContext();
    $context['profile']
        ->addMedia(UploadedFile::fake()->image('portfolio.jpg'))
        ->toMediaCollection(ArtisanProfile::PORTFOLIO_COLLECTION);
    $requested = phaseSixCreateBooking($context)->booking;
    $rejectable = phaseSixCreateBooking($context)->booking;
    $accepted = Booking::factory()->accepted()->create([
        'customer_id' => $context['customer']->id,
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['category']->id,
    ]);
    $inProgress = Booking::factory()->inProgress()->create([
        'customer_id' => $context['customer']->id,
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['category']->id,
    ]);

    $this->get(route('marketplace.index', [
        'query' => 'Electrical',
        'service_category_id' => $context['category']->id,
        'state_id' => $context['state']->id,
        'local_government_id' => $context['localGovernment']->id,
        'territory_id' => $context['territory']->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('artisans.0.businessName', 'Phase Six Electrical')
            ->where('categories.0.id', $context['category']->id));

    $this->get(route('marketplace.artisans.show', ['artisanProfile' => $context['profile']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Show')
            ->where('artisan.businessName', 'Phase Six Electrical')
            ->where('artisan.services.0.title', 'Phase Six Electrical Repair')
            ->where('artisan.portfolio.0.name', 'portfolio'));

    $this->get(route('marketplace.bookings.create', ['artisanProfile' => $context['profile']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Book')
            ->where('artisan.id', $context['profile']->id));

    $this->actingAs($context['owner'])
        ->get(route('artisan.bookings.index', ['current_team' => $context['team']->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Bookings')
            ->where('bookings.0.id', $inProgress->id));

    $this->actingAs($context['owner'])
        ->post(route('artisan.bookings.accept', ['current_team' => $context['team']->slug, 'booking' => $requested]))
        ->assertRedirect(route('artisan.bookings.index', ['current_team' => $context['team']->slug]));

    $this->actingAs($context['owner'])
        ->post(route('artisan.bookings.reject', ['current_team' => $context['team']->slug, 'booking' => $rejectable]))
        ->assertRedirect(route('artisan.bookings.index', ['current_team' => $context['team']->slug]));

    $this->actingAs($context['owner'])
        ->post(route('artisan.bookings.start', ['current_team' => $context['team']->slug, 'booking' => $accepted]))
        ->assertRedirect(route('artisan.bookings.index', ['current_team' => $context['team']->slug]));

    $this->actingAs($context['owner'])
        ->post(route('artisan.bookings.finish', ['current_team' => $context['team']->slug, 'booking' => $inProgress]))
        ->assertRedirect(route('artisan.bookings.index', ['current_team' => $context['team']->slug]));

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.confirm', ['booking' => $inProgress->refresh()]))
        ->assertRedirect(route('customer.bookings.show', ['booking' => $inProgress]));
});
