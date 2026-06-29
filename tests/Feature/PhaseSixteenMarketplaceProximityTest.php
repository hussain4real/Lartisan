<?php

use App\Actions\Artisans\RecordFieldVisit;
use App\Actions\Bookings\SearchArtisans;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\FieldVisitStatus;
use App\Models\Address;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Country;
use App\Models\CustomerProfile;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Territory;
use App\Models\User;
use App\Support\Marketplace\ProximitySearch;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * @return array{country: Country, state: State, localGovernment: LocalGovernment, territory: Territory}
 */
function phaseSixteenGeography(string $label = 'Primary'): array
{
    $slug = Str::slug($label).'-'.Str::lower(Str::random(6));
    $country = Country::factory()->create([
        'name' => "Phase 16 {$label} Country",
        'iso_code' => Str::upper(Str::random(2)),
    ]);
    $state = State::factory()->create([
        'country_id' => $country->id,
        'name' => "Phase 16 {$label} State",
        'slug' => "{$slug}-state",
    ]);
    $localGovernment = LocalGovernment::factory()->create([
        'state_id' => $state->id,
        'name' => "Phase 16 {$label} LGA",
        'slug' => "{$slug}-lga",
    ]);
    $territory = Territory::factory()->create([
        'local_government_id' => $localGovernment->id,
        'name' => "Phase 16 {$label} Territory",
        'slug' => "{$slug}-territory",
    ]);

    return [
        'country' => $country,
        'state' => $state,
        'localGovernment' => $localGovernment,
        'territory' => $territory,
    ];
}

function phaseSixteenMarketplaceArtisan(
    string $businessName,
    ServiceCategory $category,
    Territory $territory,
    string $latitude,
    string $longitude,
    int $serviceRadiusKm = 100,
): ArtisanProfile {
    $localGovernment = $territory->localGovernment()->firstOrFail();
    $state = $localGovernment->state()->firstOrFail();
    $country = $state->country()->firstOrFail();
    $profile = ArtisanProfile::factory()->create([
        'business_name' => $businessName,
        'public_summary' => "{$businessName} serves nearby customers.",
        'verification_status' => ArtisanVerificationStatus::Approved,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $localGovernment->id,
        'territory_id' => $territory->id,
        'marketplace_latitude' => $latitude,
        'marketplace_longitude' => $longitude,
        'marketplace_coordinates_verified_at' => now()->subDay(),
        'service_radius_km' => $serviceRadiusKm,
        'is_public' => true,
        'approved_at' => now()->subDay(),
    ]);

    ArtisanService::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'title' => "{$businessName} Service",
    ]);
    Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
    ]);

    return $profile->refresh();
}

test('phase sixteen proximity inputs are validated before marketplace search', function (): void {
    $this->get(route('marketplace.index', [
        'near_lat' => 91,
        'near_lng' => 181,
        'radius_km' => 101,
    ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['near_lat', 'near_lng', 'radius_km']);

    $this->get(route('marketplace.index', [
        'near_lat' => '9.076',
    ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['near_lng']);

    $this->get(route('marketplace.index', [
        'radius_km' => 25,
    ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['radius_km']);

    $this->get(route('marketplace.index', [
        'near_lat' => 'bad',
        'near_lng' => 'bad',
    ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['near_lat', 'near_lng']);

    $this->get(route('marketplace.index', [
        'near_lat' => ['9.076'],
        'near_lng' => ['7.469'],
    ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['near_lat', 'near_lng']);
});

test('completed field visits publish verified marketplace coordinates', function (): void {
    $geography = phaseSixteenGeography('Field Visit');
    $profile = ArtisanProfile::factory()->create([
        'country_id' => $geography['country']->id,
        'state_id' => $geography['state']->id,
        'local_government_id' => $geography['localGovernment']->id,
        'territory_id' => $geography['territory']->id,
        'marketplace_latitude' => null,
        'marketplace_longitude' => null,
        'marketplace_coordinates_verified_at' => null,
    ]);
    $visitedAt = now()->subHour()->startOfSecond();

    app(RecordFieldVisit::class)->handle(
        profile: $profile,
        areaAgent: User::factory()->create(),
        territory: $geography['territory'],
        status: FieldVisitStatus::Completed,
        visitedAt: $visitedAt,
        latitude: '9.0764780',
        longitude: '7.4686590',
        notes: 'Verified shop coordinate.',
    );

    $profile->refresh();

    expect($profile->marketplace_latitude)->toBe('9.0764780')
        ->and($profile->marketplace_longitude)->toBe('7.4686590')
        ->and($profile->marketplace_coordinates_verified_at?->equalTo($visitedAt))->toBeTrue();
});

test('marketplace proximity ranks nearby verified artisans and exposes distance labels', function (): void {
    $geography = phaseSixteenGeography('Ranking');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase 16 Ranking Services',
        'slug' => 'phase-16-ranking-services',
    ]);

    phaseSixteenMarketplaceArtisan('Phase 16 Near Artisan', $category, $geography['territory'], '9.0764780', '7.4686590');
    phaseSixteenMarketplaceArtisan('Phase 16 Far Artisan', $category, $geography['territory'], '9.2300000', '7.6100000');
    phaseSixteenMarketplaceArtisan('Phase 16 Outside Radius', $category, $geography['territory'], '10.0000000', '8.2000000');

    $this->get(route('marketplace.index', [
        'service_category_id' => $category->id,
        'near_lat' => '9.0764789',
        'near_lng' => '7.4686599',
        'radius_km' => 50,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('filters.nearLat', 9.076)
            ->where('filters.nearLng', 7.469)
            ->where('filters.radiusKm', 50)
            ->where('artisans.total', 2)
            ->where('artisans.data.0.businessName', 'Phase 16 Near Artisan')
            ->where('artisans.data.0.distanceLabel', 'Less than 1 km away')
            ->where('artisans.data.1.businessName', 'Phase 16 Far Artisan')
            ->where('artisans.data.1.distanceKm', 23.1));
});

test('manual geography filters remain authoritative during proximity search', function (): void {
    $primary = phaseSixteenGeography('Manual Primary');
    $other = phaseSixteenGeography('Manual Other');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase 16 Manual Filter Services',
        'slug' => 'phase-16-manual-filter-services',
    ]);

    phaseSixteenMarketplaceArtisan('Phase 16 Nearby Other Territory', $category, $other['territory'], '9.0764780', '7.4686590');
    phaseSixteenMarketplaceArtisan('Phase 16 Selected Territory', $category, $primary['territory'], '9.0900000', '7.4800000');

    $this->get(route('marketplace.index', [
        'service_category_id' => $category->id,
        'state_id' => $primary['state']->id,
        'local_government_id' => $primary['localGovernment']->id,
        'territory_id' => $primary['territory']->id,
        'near_lat' => '9.076',
        'near_lng' => '7.469',
        'radius_km' => 50,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('filters.stateId', $primary['state']->id)
            ->where('filters.localGovernmentId', $primary['localGovernment']->id)
            ->where('filters.territoryId', $primary['territory']->id)
            ->where('artisans.total', 1)
            ->where('artisans.data.0.businessName', 'Phase 16 Selected Territory'));
});

test('proximity search respects each artisan service radius', function (): void {
    $geography = phaseSixteenGeography('Service Radius');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase 16 Radius Services',
        'slug' => 'phase-16-radius-services',
    ]);

    phaseSixteenMarketplaceArtisan('Phase 16 Radius Match', $category, $geography['territory'], '9.0800000', '7.4700000', 10);
    phaseSixteenMarketplaceArtisan('Phase 16 Radius Excluded', $category, $geography['territory'], '9.1800000', '7.5700000', 5);

    $this->get(route('marketplace.index', [
        'service_category_id' => $category->id,
        'near_lat' => '9.076',
        'near_lng' => '7.469',
        'radius_km' => 50,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('artisans.total', 1)
            ->where('artisans.data.0.businessName', 'Phase 16 Radius Match'));
});

test('proximity search applies circular radius filtering after bounding box prefiltering', function (): void {
    $geography = phaseSixteenGeography('Circular Radius');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase 16 Circular Radius Services',
        'slug' => 'phase-16-circular-radius-services',
    ]);

    phaseSixteenMarketplaceArtisan('Phase 16 Circular Radius Match', $category, $geography['territory'], '9.0800000', '7.4700000', 100);
    phaseSixteenMarketplaceArtisan('Phase 16 Circular Radius Excluded', $category, $geography['territory'], '9.1460000', '7.5390000', 100);

    $this->get(route('marketplace.index', [
        'service_category_id' => $category->id,
        'near_lat' => '9.076',
        'near_lng' => '7.469',
        'radius_km' => 10,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('artisans.total', 1)
            ->where('artisans.data.0.businessName', 'Phase 16 Circular Radius Match'));
});

test('proximity ranking uses distance and business name tie breakers', function (): void {
    $geography = phaseSixteenGeography('Tie Breakers');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase 16 Tie Breaker Services',
        'slug' => 'phase-16-tie-breaker-services',
    ]);
    $proximity = new ProximitySearch(latitude: 9.076, longitude: 7.469, radiusKm: 100);

    phaseSixteenMarketplaceArtisan('Phase 16 Same Score Far', $category, $geography['territory'], '9.0885000', '7.4690000');
    phaseSixteenMarketplaceArtisan('Phase 16 Same Score Near', $category, $geography['territory'], '9.0860000', '7.4690000');
    phaseSixteenMarketplaceArtisan('Phase 16 Alpha Tie', $category, $geography['territory'], '9.1030000', '7.4690000');
    phaseSixteenMarketplaceArtisan('Phase 16 Zulu Tie', $category, $geography['territory'], '9.1030000', '7.4690000');

    $results = app(SearchArtisans::class)->handle(
        category: $category,
        proximity: $proximity,
        limit: 10,
    );

    expect($results->pluck('business_name')->all())->toBe([
        'Phase 16 Same Score Near',
        'Phase 16 Same Score Far',
        'Phase 16 Alpha Tie',
        'Phase 16 Zulu Tie',
    ])->and($proximity->distanceTo(null, '7.4690000'))->toBeNull();
});

test('customer proximity queries do not persist precise user coordinates', function (): void {
    $geography = phaseSixteenGeography('Privacy');
    $category = ServiceCategory::factory()->create([
        'name' => 'Phase 16 Privacy Services',
        'slug' => 'phase-16-privacy-services',
    ]);
    phaseSixteenMarketplaceArtisan('Phase 16 Privacy Artisan', $category, $geography['territory'], '9.0764780', '7.4686590');
    $customer = User::factory()->create();
    $profile = CustomerProfile::factory()->create([
        'user_id' => $customer->id,
        'preferences' => ['preferred_channel' => 'whatsapp'],
    ]);

    $this->actingAs($customer)
        ->get(route('marketplace.index', [
            'near_lat' => '9.0764789',
            'near_lng' => '7.4686599',
            'radius_km' => 25,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('filters.nearLat', 9.076)
            ->where('filters.nearLng', 7.469)
            ->where('artisans.data.0.businessName', 'Phase 16 Privacy Artisan'));

    expect($profile->refresh()->preferences)->toBe(['preferred_channel' => 'whatsapp'])
        ->and(Address::query()->where('user_id', $customer->id)->count())->toBe(0);

    $previousUrl = session('_previous.url');

    expect(is_string($previousUrl))->toBeTrue();

    $previousUrl = is_string($previousUrl) ? $previousUrl : '';

    expect(str_contains($previousUrl, '9.0764789'))->toBeFalse()
        ->and(str_contains($previousUrl, '7.4686599'))->toBeFalse()
        ->and($previousUrl)->toContain('near_lat=9.076')
        ->toContain('near_lng=7.469');
});
