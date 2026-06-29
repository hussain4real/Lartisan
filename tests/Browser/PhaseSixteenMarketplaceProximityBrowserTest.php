<?php

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\ServiceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

test('phase sixteen browser proximity controls handle location success clearing radius and fallbacks', function (): void {
    createPhaseSixteenBrowserMarketplaceProfile();

    visit(route('marketplace.index', absolute: false))
        ->geolocation(9.0764789, 7.4686599)
        ->assertSee('Use my location')
        ->click('@use-location-button')
        ->assertSee('Showing nearby artisans.')
        ->assertQueryStringHas('near_lat', '9.076')
        ->assertQueryStringHas('near_lng', '7.469')
        ->assertQueryStringHas('radius_km', '25')
        ->assertSee('Less than 1 km away')
        ->select('@radius-select', '50')
        ->assertQueryStringHas('radius_km', '50')
        ->click('@clear-location-button')
        ->assertQueryStringMissing('near_lat')
        ->assertQueryStringMissing('near_lng')
        ->assertNoSmoke();

    $permissionDeniedPage = visit(route('marketplace.index', absolute: false));
    $permissionDeniedPage->script("Object.defineProperty(navigator, 'geolocation', { configurable: true, value: { getCurrentPosition: (_success, failure) => failure({ code: 1, PERMISSION_DENIED: 1 }) } });");

    $permissionDeniedPage
        ->click('@use-location-button')
        ->assertSee('Location permission denied. Use filters instead.')
        ->assertNoSmoke();

    $unavailablePage = visit(route('marketplace.index', absolute: false));
    $unavailablePage->script("Object.defineProperty(navigator, 'geolocation', { configurable: true, value: undefined });");

    $unavailablePage
        ->click('@use-location-button')
        ->assertSee('Location unavailable. Use filters instead.')
        ->assertNoSmoke();
})->group('smoke');

function createPhaseSixteenBrowserMarketplaceProfile(): ArtisanProfile
{
    $profile = ArtisanProfile::factory()->create([
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'business_name' => 'Phase 16 Browser Artisan',
        'is_public' => true,
        'marketplace_coordinates_verified_at' => now()->subDay(),
        'marketplace_latitude' => '9.0764780',
        'marketplace_longitude' => '7.4686590',
        'service_radius_km' => 25,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'verification_status' => ArtisanVerificationStatus::Approved,
    ]);

    $plan = SubscriptionPlan::factory()->create(['active' => true]);

    Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);

    ArtisanService::factory()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => ServiceCategory::factory()->create(['active' => true])->id,
        'status' => ArtisanServiceStatus::Active,
    ]);

    return $profile->refresh();
}
