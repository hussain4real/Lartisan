<?php

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\WaitlistAudienceType;
use App\Filament\Resources\WaitlistEntries\Pages\ListWaitlistEntries;
use App\Filament\Resources\WaitlistEntries\Pages\ViewWaitlistEntry;
use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use App\Models\AdminProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Policies\WaitlistEntryPolicy;
use Database\Seeders\PlatformAccessSeeder;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(PlatformAccessSeeder::class);
});

/**
 * @return array{superAdmin: User, stateCoordinator: User, localGovernmentAdmin: User, areaAgent: User, customer: User}
 */
function waitlistFilamentUsers(): array
{
    $superAdmin = User::factory()->create(['name' => 'Super Admin']);
    $superAdmin->assignRole(PlatformRole::SuperAdmin->value);

    $stateCoordinator = User::factory()->create(['name' => 'State Coordinator']);
    $stateCoordinator->assignRole(PlatformRole::StateCoordinator->value);

    $localGovernmentAdmin = User::factory()->create(['name' => 'LGA Admin']);
    $localGovernmentAdmin->assignRole(PlatformRole::LocalGovernmentAdmin->value);

    $areaAgent = User::factory()->create(['name' => 'Area Agent']);
    $areaAgent->assignRole(PlatformRole::AreaAgent->value);

    $customer = User::factory()->create(['name' => 'Customer']);
    $customer->assignRole(PlatformRole::Customer->value);

    return [
        'superAdmin' => $superAdmin,
        'stateCoordinator' => $stateCoordinator,
        'localGovernmentAdmin' => $localGovernmentAdmin,
        'areaAgent' => $areaAgent,
        'customer' => $customer,
    ];
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  class-string<TComponent>  $component
 * @param  array<string, mixed>  $params
 * @return Testable<TComponent>
 */
function waitlistFilamentLivewire(User $user, string $panel, string $component, array $params = []): Testable
{
    Filament::setCurrentPanel($panel);
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

test('waitlist resource permission is assigned to operations admins', function (): void {
    $users = waitlistFilamentUsers();

    expect($users['superAdmin']->can(PlatformPermission::ViewWaitlistEntries->value))->toBeTrue()
        ->and($users['stateCoordinator']->can(PlatformPermission::ViewWaitlistEntries->value))->toBeTrue()
        ->and($users['localGovernmentAdmin']->can(PlatformPermission::ViewWaitlistEntries->value))->toBeTrue()
        ->and($users['areaAgent']->can(PlatformPermission::ViewWaitlistEntries->value))->toBeFalse()
        ->and($users['customer']->can(PlatformPermission::ViewWaitlistEntries->value))->toBeFalse();
});

test('waitlist resource access and query are permission gated', function (): void {
    $users = waitlistFilamentUsers();
    $entry = WaitlistEntry::factory()->create();
    WaitlistEntry::factory()->create();
    $policy = new WaitlistEntryPolicy;

    $this->actingAs($users['stateCoordinator']);
    expect(WaitlistEntryResource::canAccess())->toBeTrue()
        ->and(WaitlistEntryResource::getEloquentQuery()->count())->toBe(0)
        ->and($policy->viewAny($users['stateCoordinator']))->toBeTrue()
        ->and($policy->view($users['stateCoordinator'], $entry))->toBeFalse()
        ->and($policy->create($users['stateCoordinator']))->toBeFalse()
        ->and($policy->update($users['stateCoordinator'], $entry))->toBeFalse()
        ->and($policy->delete($users['stateCoordinator'], $entry))->toBeFalse()
        ->and($policy->restore($users['stateCoordinator'], $entry))->toBeFalse()
        ->and($policy->forceDelete($users['stateCoordinator'], $entry))->toBeFalse();

    $this->actingAs($users['areaAgent']);
    expect(WaitlistEntryResource::canAccess())->toBeFalse()
        ->and(WaitlistEntryResource::getEloquentQuery()->count())->toBe(0)
        ->and($policy->viewAny($users['areaAgent']))->toBeFalse()
        ->and($policy->view($users['areaAgent'], $entry))->toBeFalse();

    auth()->logout();
    expect(WaitlistEntryResource::canAccess())->toBeFalse()
        ->and(WaitlistEntryResource::getEloquentQuery()->count())->toBe(0);
});

test('waitlist resource query is scoped to the operations admin geography', function (): void {
    $users = waitlistFilamentUsers();
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $state = State::factory()->for($country)->create(['name' => 'FCT']);
    $otherState = State::factory()->for($country)->create(['name' => 'Lagos']);
    $localGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'AMAC']);
    $otherLocalGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'Bwari']);
    $outsideStateLocalGovernment = LocalGovernment::factory()->for($otherState)->create(['name' => 'Ikeja']);
    $territory = Territory::factory()->for($localGovernment)->create(['name' => 'Wuse']);
    $otherTerritory = Territory::factory()->for($otherLocalGovernment)->create(['name' => 'Kubwa']);
    $outsideStateTerritory = Territory::factory()->for($outsideStateLocalGovernment)->create(['name' => 'Allen']);

    AdminProfile::factory()->for($users['stateCoordinator'])->create([
        'role' => PlatformRole::StateCoordinator,
        'scope_type' => $state->getMorphClass(),
        'scope_id' => $state->id,
    ]);
    AdminProfile::factory()->for($users['localGovernmentAdmin'])->create([
        'role' => PlatformRole::LocalGovernmentAdmin,
        'scope_type' => $localGovernment->getMorphClass(),
        'scope_id' => $localGovernment->id,
    ]);

    $localEntry = WaitlistEntry::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $localGovernment->id,
        'territory_id' => $territory->id,
    ]);
    $sameStateEntry = WaitlistEntry::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $otherLocalGovernment->id,
        'territory_id' => $otherTerritory->id,
    ]);
    $outsideStateEntry = WaitlistEntry::factory()->create([
        'country_id' => $country->id,
        'state_id' => $otherState->id,
        'local_government_id' => $outsideStateLocalGovernment->id,
        'territory_id' => $outsideStateTerritory->id,
    ]);

    $this->actingAs($users['superAdmin']);
    expect(WaitlistEntryResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$localEntry->id, $sameStateEntry->id, $outsideStateEntry->id]);

    $this->actingAs($users['stateCoordinator']);
    expect(WaitlistEntryResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$localEntry->id, $sameStateEntry->id])
        ->and((new WaitlistEntryPolicy)->view($users['stateCoordinator'], $outsideStateEntry))->toBeFalse();

    $this->actingAs($users['localGovernmentAdmin']);
    expect(WaitlistEntryResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$localEntry->id])
        ->and((new WaitlistEntryPolicy)->view($users['localGovernmentAdmin'], $sameStateEntry))->toBeFalse();
});

test('waitlist visibility rejects unsupported and invalid admin scopes', function (): void {
    $users = waitlistFilamentUsers();
    WaitlistEntry::factory()->create();

    AdminProfile::factory()->for($users['areaAgent'])->create([
        'role' => PlatformRole::AreaAgent,
    ]);
    AdminProfile::factory()->for($users['stateCoordinator'])->create([
        'role' => PlatformRole::StateCoordinator,
        'scope_type' => null,
        'scope_id' => null,
    ]);
    AdminProfile::factory()->for($users['localGovernmentAdmin'])->create([
        'role' => PlatformRole::LocalGovernmentAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    expect(WaitlistEntry::query()->visibleTo($users['areaAgent'])->count())->toBe(0)
        ->and(WaitlistEntry::query()->visibleTo($users['stateCoordinator'])->count())->toBe(0)
        ->and(WaitlistEntry::query()->visibleTo($users['localGovernmentAdmin'])->count())->toBe(0);
});

test('waitlist resource is read only and renders index and view pages', function (): void {
    $users = waitlistFilamentUsers();
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $state = State::factory()->for($country)->create(['name' => 'FCT']);
    $localGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'AMAC']);
    $territory = Territory::factory()->for($localGovernment)->create(['name' => 'Wuse']);
    $category = ServiceCategory::factory()->create(['name' => 'Electrical']);
    AdminProfile::factory()->for($users['stateCoordinator'])->create([
        'role' => PlatformRole::StateCoordinator,
        'scope_type' => $state->getMorphClass(),
        'scope_id' => $state->id,
    ]);
    AdminProfile::factory()->for($users['localGovernmentAdmin'])->create([
        'role' => PlatformRole::LocalGovernmentAdmin,
        'scope_type' => $localGovernment->getMorphClass(),
        'scope_id' => $localGovernment->id,
    ]);
    $entry = WaitlistEntry::factory()->create([
        'name' => 'Amina Bello',
        'email' => 'amina@example.com',
        'phone' => '+234 800 000 0000',
        'audience_type' => WaitlistAudienceType::Artisan,
        'business_name' => 'Bright Sparks Electrical',
        'service_category_id' => $category->id,
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $localGovernment->id,
        'territory_id' => $territory->id,
        'note' => 'Ready for launch.',
    ]);

    expect(WaitlistEntryResource::canCreate())->toBeFalse()
        ->and(array_keys(WaitlistEntryResource::getPages()))->toBe(['index', 'view'])
        ->and(WaitlistEntryResource::getRelations())->toBe([])
        ->and(WaitlistEntryResource::infolist(Schema::make())->getComponents())->toHaveCount(14);

    waitlistFilamentLivewire($users['stateCoordinator'], 'state', ListWaitlistEntries::class)
        ->assertCanSeeTableRecords([$entry])
        ->assertTableActionExists('view', null, $entry->getRouteKey());

    waitlistFilamentLivewire($users['localGovernmentAdmin'], 'lga', ViewWaitlistEntry::class, ['record' => $entry->getRouteKey()])
        ->assertOk()
        ->assertSee('Amina Bello')
        ->assertSee('amina@example.com')
        ->assertSee('Electrical')
        ->assertSee('Wuse');
});
