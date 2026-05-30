<?php

use App\Enums\AdminProfileStatus;
use App\Enums\PlatformRole;
use App\Enums\TerritoryType;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\GeographySeeder;

test('geography seeder creates idempotent nigeria pilot data', function () {
    $this->seed(GeographySeeder::class);
    $this->seed(GeographySeeder::class);

    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $fct = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $amac = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $wuseMarket = Territory::query()->where('slug', 'wuse-market')->firstOrFail();

    expect(Country::query()->count())->toBe(1);
    expect($country->states()->count())->toBe(37);
    expect($fct->country->is($country))->toBeTrue();
    expect($fct->localGovernments()->count())->toBe(6);
    expect($amac->state->is($fct))->toBeTrue();
    expect($amac->territories()->count())->toBe(4);
    expect(Territory::query()->count())->toBe(15);
    expect($wuseMarket->localGovernment->is($amac))->toBeTrue();
    expect($wuseMarket->type)->toBe(TerritoryType::Market);
    expect($wuseMarket->active)->toBeTrue();
});

test('admin profile and area assignment relationships resolve operational scopes', function () {
    $this->seed(GeographySeeder::class);

    $superAdmin = User::factory()->create();
    $stateCoordinator = User::factory()->create();
    $agent = User::factory()->create();
    $fct = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();

    $profile = AdminProfile::factory()->create([
        'user_id' => $stateCoordinator->id,
        'role' => PlatformRole::StateCoordinator,
        'scope_type' => State::class,
        'scope_id' => $fct->id,
        'appointed_by' => $superAdmin->id,
        'status' => AdminProfileStatus::Active,
        'appointed_at' => now(),
    ]);

    $assignment = AreaAgentAssignment::factory()->create([
        'user_id' => $agent->id,
        'territory_id' => $territory->id,
        'assigned_by' => $stateCoordinator->id,
        'reason' => 'Pilot market coverage',
    ]);

    expect($profile->user->is($stateCoordinator))->toBeTrue();
    expect($profile->appointedBy->is($superAdmin))->toBeTrue();
    expect($profile->scope->is($fct))->toBeTrue();
    expect($profile->role)->toBe(PlatformRole::StateCoordinator);
    expect($profile->status)->toBe(AdminProfileStatus::Active);
    expect($profile->appointed_at->isToday())->toBeTrue();

    expect($assignment->user->is($agent))->toBeTrue();
    expect($assignment->territory->is($territory))->toBeTrue();
    expect($assignment->assignedBy->is($stateCoordinator))->toBeTrue();
    expect($assignment->starts_at->lessThanOrEqualTo(now()))->toBeTrue();
    expect($territory->areaAgentAssignments()->first()->is($assignment))->toBeTrue();
});
