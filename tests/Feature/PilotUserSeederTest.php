<?php

use App\Actions\Bookings\SearchArtisans;
use App\Actions\Setup\SeedMarketplaceCatalog;
use App\Enums\AdminProfileStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformRole;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\CustomerProfile;
use App\Models\KycSubmission;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use App\Support\Marketplace\ProximitySearch;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PilotUserSeeder;
use Database\Seeders\SubscriptionPlanSeeder;

test('pilot user seeder creates idempotent role scoped demo accounts', function () {
    $this->seed(PilotUserSeeder::class);
    $this->seed(PilotUserSeeder::class);

    $superAdmin = User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail();
    $stateCoordinator = User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail();
    $localGovernmentAdmin = User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail();
    $areaAgent = User::query()->where('email', 'area.agent@lartisan.test')->firstOrFail();
    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();
    $fct = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $amac = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $artisanProfile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();

    expect(User::query()->count())->toBe(26);
    expect(Team::query()->where('is_personal', true)->count())->toBe(26);
    expect(Team::query()->where('is_personal', false)->count())->toBe(21);
    expect(AdminProfile::query()->count())->toBe(4);
    expect(AreaAgentAssignment::query()->count())->toBe(2);
    expect(ArtisanProfile::query()->count())->toBe(21);
    expect(ServiceCategory::query()->count())->toBe(12);
    expect(ArtisanService::query()->count())->toBe(201);
    expect(Subscription::query()->count())->toBe(20);
    expect(KycSubmission::query()->count())->toBe(1);
    expect(CustomerProfile::query()->count())->toBe(1);
    expect(Address::query()->count())->toBe(1);

    expect($superAdmin->hasRole(PlatformRole::SuperAdmin->value))->toBeTrue();
    expect($stateCoordinator->hasRole(PlatformRole::StateCoordinator->value))->toBeTrue();
    expect($localGovernmentAdmin->hasRole(PlatformRole::LocalGovernmentAdmin->value))->toBeTrue();
    expect($areaAgent->hasRole(PlatformRole::AreaAgent->value))->toBeTrue();
    expect($artisan->hasRole(PlatformRole::Artisan->value))->toBeTrue();
    expect($customer->hasRole(PlatformRole::Customer->value))->toBeTrue();

    $stateCoordinatorProfile = AdminProfile::query()->where('user_id', $stateCoordinator->id)->firstOrFail();
    $localGovernmentAdminProfile = AdminProfile::query()->where('user_id', $localGovernmentAdmin->id)->firstOrFail();
    $areaAgentProfile = AdminProfile::query()->where('user_id', $areaAgent->id)->firstOrFail();

    expect($stateCoordinatorProfile->role)->toBe(PlatformRole::StateCoordinator);
    expect($stateCoordinatorProfile->status)->toBe(AdminProfileStatus::Active);
    expect($stateCoordinatorProfile->scope()->firstOrFail()->is($fct))->toBeTrue();
    expect($stateCoordinatorProfile->appointedBy()->firstOrFail()->is($superAdmin))->toBeTrue();
    expect($localGovernmentAdminProfile->scope()->firstOrFail()->is($amac))->toBeTrue();
    expect($localGovernmentAdminProfile->appointedBy()->firstOrFail()->is($stateCoordinator))->toBeTrue();
    expect($areaAgentProfile->scope()->firstOrFail()->is($amac))->toBeTrue();
    expect($areaAgentProfile->appointedBy()->firstOrFail()->is($localGovernmentAdmin))->toBeTrue();

    $assignedTerritories = AreaAgentAssignment::query()
        ->where('user_id', $areaAgent->id)
        ->with('territory')
        ->get()
        ->pluck('territory.slug')
        ->sort()
        ->values()
        ->all();

    expect($assignedTerritories)->toBe(['garki-market', 'wuse-market']);
    expect($artisanProfile->business_name)->toBe('Wuse Sparks Electrical');
    expect($artisanProfile->team()->firstOrFail()->is_personal)->toBeFalse();
    expect($artisanProfile->user()->firstOrFail()->is($artisan))->toBeTrue();
    expect($artisanProfile->onboardedByAgent()->firstOrFail()->is($areaAgent))->toBeTrue();
    expect($artisanProfile->country()->firstOrFail()->iso_code)->toBe('NG');
    expect($artisanProfile->state()->firstOrFail()->is($fct))->toBeTrue();
    expect($artisanProfile->localGovernment()->firstOrFail()->is($amac))->toBeTrue();
    expect($artisanProfile->territory()->firstOrFail()->slug)->toBe('wuse-market');
    expect($artisanProfile->marketplace_latitude)->toBe('9.0764780');
    expect($artisanProfile->marketplace_longitude)->toBe('7.4686590');
    expect($artisanProfile->marketplace_coordinates_verified_at)->not->toBeNull();
    expect($artisanProfile->services()->firstOrFail()->status)->toBe(ArtisanServiceStatus::Active);
    expect($artisanProfile->kycSubmissions()->firstOrFail()->status)->toBe(ArtisanVerificationStatus::Submitted);
    expect($customer->artisanProfiles()->exists())->toBeFalse();
    expect($customer->customerProfile()->firstOrFail()->preferences)->toBe([
        'preferred_channel' => 'whatsapp',
        'service_area' => 'Wuse',
    ]);
    expect($customer->customerProfile()->firstOrFail()->defaultAddress()->firstOrFail()->label)->toBe('Home');
});

test('pilot user seeder creates a broad marketplace catalog', function () {
    $this->seed(PilotUserSeeder::class);
    $this->seed(PilotUserSeeder::class);

    $catalogProfiles = ArtisanProfile::query()
        ->where('business_name', '!=', 'Wuse Sparks Electrical')
        ->get();

    expect($catalogProfiles)->toHaveCount(20);
    expect(ArtisanService::query()->count())->toBeGreaterThanOrEqual(200);
    expect(ArtisanService::query()->where('status', ArtisanServiceStatus::Active)->count())->toBe(201);
    expect(ArtisanService::query()->where('description', 'like', 'Service:%')->count())->toBeGreaterThan(0);
    expect(ArtisanService::query()->where('description', 'like', 'Product:%')->count())->toBeGreaterThan(0);
    expect(ArtisanService::query()->distinct()->count('service_category_id'))->toBe(12);
    expect(ArtisanService::query()->distinct()->count('artisan_profile_id'))->toBe(21);
    expect($catalogProfiles->pluck('state_id')->unique()->count())->toBeGreaterThanOrEqual(5);
    expect($catalogProfiles->pluck('local_government_id')->unique()->count())->toBeGreaterThanOrEqual(10);
    expect($catalogProfiles->pluck('territory_id')->unique()->count())->toBeGreaterThanOrEqual(20);
    expect($catalogProfiles->pluck('marketplace_latitude')->filter()->count())->toBe(20);
    expect($catalogProfiles->pluck('marketplace_longitude')->filter()->count())->toBe(20);
    expect($catalogProfiles->pluck('marketplace_coordinates_verified_at')->filter()->count())->toBe(20);
    expect($catalogProfiles
        ->map(fn (ArtisanProfile $profile): string => $profile->marketplace_latitude.','.$profile->marketplace_longitude)
        ->unique()
        ->count())->toBe(20);

    $catalogProfiles->each(function (ArtisanProfile $profile): void {
        expect($profile->services()->count())->toBe(10);
        expect($profile->subscriptions()->where('status', SubscriptionStatus::Active)->exists())->toBeTrue();
        expect($profile->is_public)->toBeTrue();
    });

    $nearWuseResults = app(SearchArtisans::class)->handle(
        proximity: new ProximitySearch(latitude: 9.076, longitude: 7.469, radiusKm: 30),
        limit: 20,
    );

    expect($nearWuseResults->pluck('business_name')->all())->toContain('Amina Home Works');
});

test('database seeder loads pilot users instead of the generic test account', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'super.admin@lartisan.test')->exists())->toBeTrue();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('marketplace catalog seeder requires enough active operating territories', function () {
    $this->seed(GeographySeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);

    Territory::query()->update(['active' => false]);

    expect(fn () => app(SeedMarketplaceCatalog::class)->handle(User::factory()->create(), []))
        ->toThrow(RuntimeException::class, 'requires at least 20 active territories');
});
