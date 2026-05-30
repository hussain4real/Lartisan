<?php

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\PlatformAccessSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('platform access seeder creates idempotent roles and permissions', function () {
    $this->seed(PlatformAccessSeeder::class);
    $this->seed(PlatformAccessSeeder::class);

    $superAdmin = Role::findByName(PlatformRole::SuperAdmin->value);
    $stateCoordinator = Role::findByName(PlatformRole::StateCoordinator->value);
    $lgaAdmin = Role::findByName(PlatformRole::LocalGovernmentAdmin->value);
    $areaAgent = Role::findByName(PlatformRole::AreaAgent->value);
    $artisan = Role::findByName(PlatformRole::Artisan->value);
    $customer = Role::findByName(PlatformRole::Customer->value);
    $guest = Role::findByName(PlatformRole::GuestCustomer->value);

    expect(Permission::query()->count())->toBe(count(PlatformPermission::cases()));
    expect(Role::query()->count())->toBe(count(PlatformRole::cases()));
    expect($superAdmin->permissions()->count())->toBe(count(PlatformPermission::cases()));
    expect($stateCoordinator->hasPermissionTo(PlatformPermission::ReviewEscalatedKyc->value))->toBeTrue();
    expect($stateCoordinator->hasPermissionTo(PlatformPermission::ManagePayouts->value))->toBeFalse();
    expect($lgaAdmin->hasPermissionTo(PlatformPermission::ReviewStandardKyc->value))->toBeTrue();
    expect($areaAgent->hasPermissionTo(PlatformPermission::SubmitFieldKyc->value))->toBeTrue();
    expect($areaAgent->hasPermissionTo(PlatformPermission::AssignTerritories->value))->toBeFalse();
    expect($artisan->hasPermissionTo(PlatformPermission::ManageOwnServices->value))->toBeTrue();
    expect($customer->hasPermissionTo(PlatformPermission::CreateReviews->value))->toBeTrue();
    expect($guest->hasPermissionTo(PlatformPermission::CreateGuestBookings->value))->toBeTrue();
    expect($guest->permissions()->count())->toBe(1);
});

test('users receive seeded platform roles through spatie permissions', function () {
    $this->seed(PlatformAccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(PlatformRole::Artisan->value);

    expect($user->hasRole(PlatformRole::Artisan->value))->toBeTrue();
    expect($user->can(PlatformPermission::ManageOwnArtisanProfile->value))->toBeTrue();
    expect($user->can(PlatformPermission::ManagePayouts->value))->toBeFalse();
});
