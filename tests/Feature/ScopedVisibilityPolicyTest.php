<?php

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PlatformRole;
use App\Models\AdminProfile;
use App\Models\ArtisanProfile;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\CustomerProfile;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\PilotUserSeeder;
use Illuminate\Support\Facades\Gate;

test('artisan profile visibility follows platform state lga territory and owner scopes', function () {
    $this->seed(PilotUserSeeder::class);

    $superAdmin = User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail();
    $stateCoordinator = User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail();
    $localGovernmentAdmin = User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail();
    $areaAgent = User::query()->where('email', 'area.agent@lartisan.test')->firstOrFail();
    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();

    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $fct = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $lagos = State::query()->where('slug', 'lagos')->firstOrFail();
    $amac = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $otherFctLocalGovernment = LocalGovernment::query()
        ->where('state_id', $fct->id)
        ->whereKeyNot($amac->id)
        ->firstOrFail();
    $wuseMarket = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
    $otherTerritory = Territory::factory()->create([
        'local_government_id' => $otherFctLocalGovernment->id,
    ]);
    $lagosLocalGovernment = LocalGovernment::factory()->create([
        'state_id' => $lagos->id,
    ]);

    $pilotProfile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();
    $sameStateDifferentLga = ArtisanProfile::factory()->create([
        'country_id' => $country->id,
        'state_id' => $fct->id,
        'local_government_id' => $otherFctLocalGovernment->id,
        'territory_id' => $otherTerritory->id,
    ]);
    $outsideState = ArtisanProfile::factory()->create([
        'country_id' => $country->id,
        'state_id' => $lagos->id,
        'local_government_id' => $lagosLocalGovernment->id,
        'territory_id' => null,
    ]);

    expect(ArtisanProfile::query()->visibleTo($superAdmin)->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id, $sameStateDifferentLga->id, $outsideState->id]);
    expect(ArtisanProfile::query()->visibleTo($stateCoordinator)->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id, $sameStateDifferentLga->id]);
    expect(ArtisanProfile::query()->visibleTo($localGovernmentAdmin)->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id]);
    expect(ArtisanProfile::query()->visibleTo($areaAgent)->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id]);
    expect(ArtisanProfile::query()->visibleTo($artisan)->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id]);
    expect(ArtisanProfile::query()->visibleTo($customer)->pluck('id')->all())->toBe([]);

    expect(Gate::forUser($stateCoordinator)->allows('view', $sameStateDifferentLga))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->denies('view', $sameStateDifferentLga))->toBeTrue();
    expect(Gate::forUser($areaAgent)->allows('view', $pilotProfile))->toBeTrue();
    expect(Gate::forUser($areaAgent)->denies('view', $sameStateDifferentLga))->toBeTrue();
    expect(ArtisanProfile::query()->inState($fct)->count())->toBe(2);
    expect(ArtisanProfile::query()->inLocalGovernment($amac)->count())->toBe(1);
    expect(ArtisanProfile::query()->inTerritory($wuseMarket)->count())->toBe(1);
});

test('admin customer and audit visibility scopes line up with policies', function () {
    $this->seed(PilotUserSeeder::class);

    $superAdmin = User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail();
    $stateCoordinator = User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail();
    $localGovernmentAdmin = User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail();
    $areaAgent = User::query()->where('email', 'area.agent@lartisan.test')->firstOrFail();
    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();

    $stateCoordinatorProfile = AdminProfile::query()->where('user_id', $stateCoordinator->id)->firstOrFail();
    $localGovernmentAdminProfile = AdminProfile::query()->where('user_id', $localGovernmentAdmin->id)->firstOrFail();
    $areaAgentProfile = AdminProfile::query()->where('user_id', $areaAgent->id)->firstOrFail();
    $customerProfile = $customer->customerProfile()->firstOrFail();
    $artisanProfile = $artisan->artisanProfiles()->firstOrFail();
    $customerLog = app(RecordAuditLog::class)->handle($customer, 'customer.profile.created', $customerProfile);
    $artisanLog = app(RecordAuditLog::class)->handle($areaAgent, 'artisan.profile.reviewed', $artisanProfile);

    expect(AdminProfile::query()->visibleTo($superAdmin)->pluck('user_id')->all())
        ->toEqualCanonicalizing([$superAdmin->id, $stateCoordinator->id, $localGovernmentAdmin->id, $areaAgent->id]);
    expect(AdminProfile::query()->visibleTo($stateCoordinator)->pluck('user_id')->all())
        ->toEqualCanonicalizing([$stateCoordinator->id, $localGovernmentAdmin->id, $areaAgent->id]);
    expect(AdminProfile::query()->visibleTo($localGovernmentAdmin)->pluck('user_id')->all())
        ->toEqualCanonicalizing([$localGovernmentAdmin->id, $areaAgent->id]);
    expect(AdminProfile::query()->forRole(PlatformRole::AreaAgent)->active()->count())->toBe(1);
    expect(AdminProfile::query()->visibleTo($customer)->pluck('user_id')->all())->toBe([]);

    $invalidStateCoordinator = User::factory()->create();
    $invalidStateCoordinator->assignRole(PlatformRole::StateCoordinator->value);
    AdminProfile::factory()->create([
        'user_id' => $invalidStateCoordinator->id,
        'role' => PlatformRole::StateCoordinator,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    $invalidLocalGovernmentAdmin = User::factory()->create();
    $invalidLocalGovernmentAdmin->assignRole(PlatformRole::LocalGovernmentAdmin->value);
    AdminProfile::factory()->create([
        'user_id' => $invalidLocalGovernmentAdmin->id,
        'role' => PlatformRole::LocalGovernmentAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    expect(AdminProfile::query()->visibleTo($invalidStateCoordinator)->pluck('user_id')->all())
        ->toBe([$invalidStateCoordinator->id]);
    expect(AdminProfile::query()->visibleTo($invalidLocalGovernmentAdmin)->pluck('user_id')->all())
        ->toBe([$invalidLocalGovernmentAdmin->id]);

    expect(Gate::forUser($stateCoordinator)->allows('view', $localGovernmentAdminProfile))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->denies('view', $stateCoordinatorProfile))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->allows('view', $areaAgentProfile))->toBeTrue();

    expect(CustomerProfile::query()->visibleTo($superAdmin)->pluck('id')->all())->toBe([$customerProfile->id]);
    expect(CustomerProfile::query()->visibleTo($customer)->pluck('id')->all())->toBe([$customerProfile->id]);
    expect(CustomerProfile::query()->visibleTo($artisan)->pluck('id')->all())->toBe([]);
    expect(Gate::forUser($customer)->allows('view', $customerProfile))->toBeTrue();
    expect(Gate::forUser($artisan)->denies('view', $customerProfile))->toBeTrue();

    expect(Gate::forUser($customer)->allows('view', $customerLog))->toBeTrue();
    expect(AuditLog::query()->visibleTo($superAdmin)->pluck('id')->all())
        ->toEqualCanonicalizing([$customerLog->id, $artisanLog->id]);
    expect(Gate::forUser($localGovernmentAdmin)->allows('view', $artisanLog))->toBeTrue();
    expect(Gate::forUser($artisan)->allows('view', $artisanLog))->toBeTrue();
    expect(Gate::forUser($customer)->denies('view', $artisanLog))->toBeTrue();
});
