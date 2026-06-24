<?php

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\TerritoryType;
use App\Filament\Resources\LocalGovernments\LocalGovernmentResource;
use App\Filament\Resources\LocalGovernments\Pages\CreateLocalGovernment;
use App\Filament\Resources\LocalGovernments\Pages\EditLocalGovernment;
use App\Filament\Resources\LocalGovernments\Pages\ListLocalGovernments;
use App\Filament\Resources\LocalGovernments\RelationManagers\TerritoriesRelationManager;
use App\Filament\Resources\States\Pages\CreateState;
use App\Filament\Resources\States\Pages\EditState;
use App\Filament\Resources\States\RelationManagers\LocalGovernmentsRelationManager;
use App\Filament\Resources\States\StateResource;
use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\AdminProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\PlatformAccessSeeder;
use Filament\Actions\CreateAction as FilamentCreateAction;
use Filament\Actions\EditAction as FilamentEditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(PlatformAccessSeeder::class);
});

/**
 * @return array{superAdmin: User, stateCoordinator: User, localGovernmentAdmin: User, areaAgent: User}
 */
function geographyFilamentUsers(State $state, LocalGovernment $localGovernment): array
{
    $superAdmin = User::factory()->create(['name' => 'Super Admin']);
    $superAdmin->assignRole(PlatformRole::SuperAdmin->value);

    $stateCoordinator = User::factory()->create(['name' => 'State Coordinator']);
    $stateCoordinator->assignRole(PlatformRole::StateCoordinator->value);
    AdminProfile::factory()->for($stateCoordinator)->create([
        'role' => PlatformRole::StateCoordinator,
        'scope_type' => $state->getMorphClass(),
        'scope_id' => $state->id,
    ]);

    $localGovernmentAdmin = User::factory()->create(['name' => 'LGA Admin']);
    $localGovernmentAdmin->assignRole(PlatformRole::LocalGovernmentAdmin->value);
    AdminProfile::factory()->for($localGovernmentAdmin)->create([
        'role' => PlatformRole::LocalGovernmentAdmin,
        'scope_type' => $localGovernment->getMorphClass(),
        'scope_id' => $localGovernment->id,
    ]);

    $areaAgent = User::factory()->create(['name' => 'Area Agent']);
    $areaAgent->assignRole(PlatformRole::AreaAgent->value);

    return [
        'superAdmin' => $superAdmin,
        'stateCoordinator' => $stateCoordinator,
        'localGovernmentAdmin' => $localGovernmentAdmin,
        'areaAgent' => $areaAgent,
    ];
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  class-string<TComponent>  $component
 * @param  array<string, mixed>  $params
 * @return Testable<TComponent>
 */
function geographyFilamentLivewire(User $user, string $panel, string $component, array $params = []): Testable
{
    Filament::setCurrentPanel($panel);
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

test('geography resource access is permission gated and scoped', function (): void {
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $state = State::factory()->for($country)->create(['name' => 'FCT']);
    $otherState = State::factory()->for($country)->create(['name' => 'Lagos']);
    $localGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'AMAC']);
    $otherLocalGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'Bwari']);
    $outsideStateLocalGovernment = LocalGovernment::factory()->for($otherState)->create(['name' => 'Ikeja']);
    $territory = Territory::factory()->for($localGovernment)->create(['name' => 'Wuse']);
    $sameStateTerritory = Territory::factory()->for($otherLocalGovernment)->create(['name' => 'Kubwa']);
    $outsideStateTerritory = Territory::factory()->for($outsideStateLocalGovernment)->create(['name' => 'Allen']);
    $users = geographyFilamentUsers($state, $localGovernment);

    expect(StateResource::getRelations())->toBe([LocalGovernmentsRelationManager::class])
        ->and(LocalGovernmentResource::getRelations())->toBe([TerritoriesRelationManager::class]);

    $this->actingAs($users['superAdmin']);
    expect(StateResource::canAccess())->toBeTrue()
        ->and(StateResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([$state->id, $otherState->id])
        ->and(LocalGovernmentResource::canAccess())->toBeTrue()
        ->and(LocalGovernmentResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([
            $localGovernment->id,
            $otherLocalGovernment->id,
            $outsideStateLocalGovernment->id,
        ])
        ->and(TerritoryResource::canAccess())->toBeTrue()
        ->and(TerritoryResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([
            $territory->id,
            $sameStateTerritory->id,
            $outsideStateTerritory->id,
        ]);

    $this->actingAs($users['stateCoordinator']);
    expect(StateResource::canAccess())->toBeTrue()
        ->and(StateResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([$state->id])
        ->and(LocalGovernmentResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([
            $localGovernment->id,
            $otherLocalGovernment->id,
        ])
        ->and(TerritoryResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([
            $territory->id,
            $sameStateTerritory->id,
        ]);

    $this->actingAs($users['localGovernmentAdmin']);
    expect(StateResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([$state->id])
        ->and(LocalGovernmentResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([$localGovernment->id])
        ->and(TerritoryResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([$territory->id]);

    $this->actingAs($users['areaAgent']);
    expect(StateResource::canAccess())->toBeFalse()
        ->and(LocalGovernmentResource::canAccess())->toBeFalse()
        ->and(TerritoryResource::canAccess())->toBeFalse()
        ->and($users['areaAgent']->can(PlatformPermission::ManageTerritories->value))->toBeFalse();
});

test('state edit page manages local governments through a relation manager', function (): void {
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $state = State::factory()->for($country)->create(['name' => 'FCT']);
    $otherState = State::factory()->for($country)->create(['name' => 'Lagos']);
    $localGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'AMAC']);
    $outsideLocalGovernment = LocalGovernment::factory()->for($otherState)->create(['name' => 'Ikeja']);
    $users = geographyFilamentUsers($state, $localGovernment);

    $manager = geographyFilamentLivewire($users['superAdmin'], 'admin', LocalGovernmentsRelationManager::class, [
        'ownerRecord' => $state,
        'pageClass' => EditState::class,
    ]);
    $manager->assertOk();

    $manager
        ->assertCanSeeTableRecords([$localGovernment])
        ->assertCanNotSeeTableRecords([$outsideLocalGovernment])
        ->callAction(TestAction::make(FilamentCreateAction::class)->table(), [
            'name' => 'Kuje',
            'slug' => 'kuje',
            'active' => true,
        ])
        ->assertNotified();

    $createdLocalGovernment = LocalGovernment::query()
        ->where('slug', 'kuje')
        ->firstOrFail();

    expect($createdLocalGovernment->state_id)->toBe($state->id);

    geographyFilamentLivewire($users['superAdmin'], 'admin', LocalGovernmentsRelationManager::class, [
        'ownerRecord' => $state,
        'pageClass' => EditState::class,
    ])
        ->callAction(TestAction::make(FilamentEditAction::class)->table($createdLocalGovernment), [
            'name' => 'Kuje Area Council',
            'slug' => 'kuje-area-council',
            'active' => false,
        ])
        ->assertNotified();

    $createdLocalGovernment->refresh();

    expect($createdLocalGovernment->name)->toBe('Kuje Area Council')
        ->and($createdLocalGovernment->state_id)->toBe($state->id)
        ->and($createdLocalGovernment->active)->toBeFalse();

    Livewire::actingAs($users['stateCoordinator']);
    expect(LocalGovernmentsRelationManager::canViewForRecord($state, EditState::class))->toBeTrue()
        ->and(LocalGovernmentsRelationManager::canViewForRecord($otherState, EditState::class))->toBeFalse();

    Livewire::actingAs($users['localGovernmentAdmin']);
    expect(LocalGovernmentsRelationManager::canViewForRecord($state, EditState::class))->toBeFalse();
});

test('lga edit page manages territories through a relation manager', function (): void {
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $state = State::factory()->for($country)->create(['name' => 'FCT']);
    $otherState = State::factory()->for($country)->create(['name' => 'Lagos']);
    $localGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'AMAC']);
    $outsideLocalGovernment = LocalGovernment::factory()->for($otherState)->create(['name' => 'Ikeja']);
    $territory = Territory::factory()->for($localGovernment)->create(['name' => 'Wuse']);
    $outsideTerritory = Territory::factory()->for($outsideLocalGovernment)->create(['name' => 'Maryland']);
    $users = geographyFilamentUsers($state, $localGovernment);

    $manager = geographyFilamentLivewire($users['stateCoordinator'], 'state', TerritoriesRelationManager::class, [
        'ownerRecord' => $localGovernment,
        'pageClass' => EditLocalGovernment::class,
    ]);
    $manager->assertOk();

    $manager
        ->assertCanSeeTableRecords([$territory])
        ->assertCanNotSeeTableRecords([$outsideTerritory])
        ->callAction(TestAction::make(FilamentCreateAction::class)->table(), [
            'type' => TerritoryType::Market->value,
            'name' => 'Garki Market',
            'slug' => 'garki-market',
            'boundaries' => '{"center":[9.01,7.49]}',
            'active' => true,
        ])
        ->assertNotified();

    $createdTerritory = Territory::query()
        ->where('slug', 'garki-market')
        ->firstOrFail();

    expect($createdTerritory->local_government_id)->toBe($localGovernment->id)
        ->and($createdTerritory->boundaries)->toBe(['center' => [9.01, 7.49]]);

    geographyFilamentLivewire($users['stateCoordinator'], 'state', TerritoriesRelationManager::class, [
        'ownerRecord' => $localGovernment,
        'pageClass' => EditLocalGovernment::class,
    ])
        ->callAction(TestAction::make(FilamentEditAction::class)->table($createdTerritory), [
            'type' => TerritoryType::Community->value,
            'name' => 'Garki Community',
            'slug' => 'garki-community',
            'boundaries' => '{"center":[9.02,7.5]}',
            'active' => false,
        ])
        ->assertNotified();

    $createdTerritory->refresh();

    expect($createdTerritory->name)->toBe('Garki Community')
        ->and($createdTerritory->type)->toBe(TerritoryType::Community)
        ->and($createdTerritory->local_government_id)->toBe($localGovernment->id)
        ->and($createdTerritory->active)->toBeFalse()
        ->and($createdTerritory->boundaries)->toBe(['center' => [9.02, 7.5]]);

    Livewire::actingAs($users['localGovernmentAdmin']);
    expect(TerritoriesRelationManager::canViewForRecord($localGovernment, EditLocalGovernment::class))->toBeTrue()
        ->and(TerritoriesRelationManager::canViewForRecord($outsideLocalGovernment, EditLocalGovernment::class))->toBeFalse();
});

test('super admins can create and update states local governments and territories', function (): void {
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $seedState = State::factory()->for($country)->create(['name' => 'Seed State']);
    $seedLocalGovernment = LocalGovernment::factory()->for($seedState)->create(['name' => 'Seed LGA']);
    $users = geographyFilamentUsers($seedState, $seedLocalGovernment);

    geographyFilamentLivewire($users['superAdmin'], 'admin', CreateState::class)
        ->fillForm([
            'country_id' => $country->id,
            'name' => 'Nasarawa',
            'slug' => 'nasarawa',
            'active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $state = State::query()->where('slug', 'nasarawa')->firstOrFail();

    $editState = geographyFilamentLivewire($users['superAdmin'], 'admin', EditState::class, ['record' => $state->getRouteKey()]);
    $editState->assertOk();
    $editState
        ->fillForm([
            'country_id' => $country->id,
            'name' => 'Nasarawa State',
            'slug' => 'nasarawa-state',
            'active' => true,
        ])
        ->call('save')
        ->assertNotified();

    expect($state->refresh()->name)->toBe('Nasarawa State');

    geographyFilamentLivewire($users['superAdmin'], 'admin', CreateLocalGovernment::class)
        ->fillForm([
            'state_id' => $state->id,
            'name' => 'Lafia',
            'slug' => 'lafia',
            'active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $localGovernment = LocalGovernment::query()->where('slug', 'lafia')->firstOrFail();

    $editLocalGovernment = geographyFilamentLivewire($users['superAdmin'], 'admin', EditLocalGovernment::class, ['record' => $localGovernment->getRouteKey()]);
    $editLocalGovernment->assertOk();
    $editLocalGovernment
        ->fillForm([
            'state_id' => $state->id,
            'name' => 'Lafia Municipal',
            'slug' => 'lafia-municipal',
            'active' => true,
        ])
        ->call('save')
        ->assertNotified();

    expect($localGovernment->refresh()->name)->toBe('Lafia Municipal');

    geographyFilamentLivewire($users['superAdmin'], 'admin', CreateTerritory::class)
        ->fillForm([
            'local_government_id' => $localGovernment->id,
            'type' => TerritoryType::Ward->value,
            'name' => 'Akunza',
            'slug' => 'akunza',
            'boundaries' => '{"center":[8.5,8.52]}',
            'active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $territory = Territory::query()->where('slug', 'akunza')->firstOrFail();

    $editTerritory = geographyFilamentLivewire($users['superAdmin'], 'admin', EditTerritory::class, ['record' => $territory->getRouteKey()]);
    $editTerritory->assertOk();
    $editTerritory
        ->fillForm([
            'local_government_id' => $localGovernment->id,
            'type' => TerritoryType::Community->value,
            'name' => 'Akunza Community',
            'slug' => 'akunza-community',
            'boundaries' => '{"center":[8.6,8.62]}',
            'active' => false,
        ])
        ->call('save')
        ->assertNotified();

    $territory->refresh();

    expect($territory->name)->toBe('Akunza Community')
        ->and($territory->type)->toBe(TerritoryType::Community)
        ->and($territory->active)->toBeFalse()
        ->and($territory->boundaries)->toBe(['center' => [8.6, 8.62]]);
});

test('state and lga admins can manage only in-scope geography records', function (): void {
    $country = Country::factory()->create(['name' => 'Nigeria']);
    $state = State::factory()->for($country)->create(['name' => 'FCT']);
    $otherState = State::factory()->for($country)->create(['name' => 'Lagos']);
    $localGovernment = LocalGovernment::factory()->for($state)->create(['name' => 'AMAC']);
    $outsideLocalGovernment = LocalGovernment::factory()->for($otherState)->create(['name' => 'Ikeja']);
    $territory = Territory::factory()->for($localGovernment)->create(['name' => 'Wuse']);
    $users = geographyFilamentUsers($state, $localGovernment);

    geographyFilamentLivewire($users['stateCoordinator'], 'state', ListLocalGovernments::class)
        ->assertCanSeeTableRecords([$localGovernment])
        ->assertCanNotSeeTableRecords([$outsideLocalGovernment]);

    geographyFilamentLivewire($users['stateCoordinator'], 'state', CreateLocalGovernment::class)
        ->fillForm([
            'state_id' => $state->id,
            'name' => 'Gwagwalada',
            'slug' => 'gwagwalada',
            'active' => true,
        ])
        ->call('create')
        ->assertNotified();

    expect(LocalGovernment::query()->where('slug', 'gwagwalada')->exists())->toBeTrue();

    expect(Gate::forUser($users['stateCoordinator'])->denies('createForState', [LocalGovernment::class, $otherState]))->toBeTrue();

    geographyFilamentLivewire($users['stateCoordinator'], 'state', CreateLocalGovernment::class)
        ->fillForm([
            'state_id' => $otherState->id,
            'name' => 'Surulere',
            'slug' => 'surulere',
            'active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['state_id']);

    expect(LocalGovernment::query()->where('slug', 'surulere')->exists())->toBeFalse();

    geographyFilamentLivewire($users['localGovernmentAdmin'], 'lga', ListTerritories::class)
        ->assertCanSeeTableRecords([$territory])
        ->assertCanNotSeeTableRecords([Territory::factory()->for($outsideLocalGovernment)->create(['name' => 'Maryland'])]);

    geographyFilamentLivewire($users['localGovernmentAdmin'], 'lga', CreateTerritory::class)
        ->fillForm([
            'local_government_id' => $localGovernment->id,
            'type' => TerritoryType::Market->value,
            'name' => 'Garki Market',
            'slug' => 'garki-market',
            'boundaries' => null,
            'active' => true,
        ])
        ->call('create')
        ->assertNotified();

    expect(Territory::query()->where('slug', 'garki-market')->exists())->toBeTrue();

    expect(Gate::forUser($users['localGovernmentAdmin'])->denies('createForLocalGovernment', [Territory::class, $outsideLocalGovernment]))->toBeTrue();

    geographyFilamentLivewire($users['localGovernmentAdmin'], 'lga', EditTerritory::class, ['record' => $territory->getRouteKey()])
        ->fillForm([
            'local_government_id' => $outsideLocalGovernment->id,
            'type' => TerritoryType::Ward->value,
            'name' => 'Moved Territory',
            'slug' => 'moved-territory',
            'boundaries' => null,
            'active' => true,
        ])
        ->call('save')
        ->assertHasFormErrors(['local_government_id']);

    expect($territory->refresh()->local_government_id)->toBe($localGovernment->id);
});
