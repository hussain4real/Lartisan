<?php

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\ServiceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

test('phase eight browser smoke covers marketplace booking tracker and operations entry points', function (): void {
    $profile = createBrowserSmokeMarketplaceProfile();
    $service = $profile->services()->firstOrFail();
    $booking = Booking::factory()->create([
        'artisan_profile_id' => $profile->id,
        'artisan_service_id' => $service->id,
        'service_category_id' => $service->service_category_id,
        'secure_token_hash' => hash('sha256', 'browser-smoke-token'),
    ]);

    visit([
        route('marketplace.index', absolute: false),
        route('marketplace.bookings.create', ['artisanProfile' => $profile], false),
        route('booking-tracker.show', ['trackerCode' => $booking->tracker_code, 'token' => 'browser-smoke-token'], false),
        route('customer.bookings.index', absolute: false),
        route('artisan.bookings.index', ['current_team' => $profile->team()->firstOrFail()->slug], false),
        '/admin',
    ])->assertNoSmoke();

    visit(route('marketplace.index', absolute: false))
        ->on()->mobile()
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();

    visit('/agent')
        ->on()->mobile()
        ->assertNoSmoke();
})->group('smoke');

function createBrowserSmokeMarketplaceProfile(): ArtisanProfile
{
    $profile = ArtisanProfile::factory()->create([
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'is_public' => true,
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
