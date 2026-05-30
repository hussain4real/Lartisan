<?php

use App\Enums\AdminProfileStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformRole;
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
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PilotUserSeeder;

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
    $artisanProfile = ArtisanProfile::query()->firstOrFail();

    expect(User::query()->count())->toBe(6);
    expect(Team::query()->where('is_personal', true)->count())->toBe(6);
    expect(Team::query()->where('is_personal', false)->count())->toBe(1);
    expect(AdminProfile::query()->count())->toBe(4);
    expect(AreaAgentAssignment::query()->count())->toBe(2);
    expect(ArtisanProfile::query()->count())->toBe(1);
    expect(ServiceCategory::query()->count())->toBe(3);
    expect(ArtisanService::query()->count())->toBe(1);
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
    expect($artisanProfile->services()->firstOrFail()->status)->toBe(ArtisanServiceStatus::Active);
    expect($artisanProfile->kycSubmissions()->firstOrFail()->status)->toBe(ArtisanVerificationStatus::Submitted);
    expect($customer->artisanProfiles()->exists())->toBeFalse();
    expect($customer->customerProfile()->firstOrFail()->preferences)->toBe([
        'preferred_channel' => 'whatsapp',
        'service_area' => 'Wuse',
    ]);
    expect($customer->customerProfile()->firstOrFail()->defaultAddress()->firstOrFail()->label)->toBe('Home');
});

test('database seeder loads pilot users instead of the generic test account', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'super.admin@lartisan.test')->exists())->toBeTrue();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});
