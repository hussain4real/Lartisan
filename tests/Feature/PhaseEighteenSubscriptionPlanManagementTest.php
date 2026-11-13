<?php

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\SubscriptionInterval;
use App\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Models\ArtisanProfile;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Policies\SubscriptionPlanPolicy;
use Database\Seeders\PlatformAccessSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    seed(PlatformAccessSeeder::class);
    Filament::setCurrentPanel('admin');
});

/**
 * @return array{superAdmin: User, stateCoordinator: User, localGovernmentAdmin: User, areaAgent: User, customer: User}
 */
function phaseEighteenUsers(): array
{
    $superAdmin = User::factory()->create(['name' => 'Phase Eighteen Super Admin']);
    $superAdmin->assignRole(PlatformRole::SuperAdmin->value);

    $stateCoordinator = User::factory()->create(['name' => 'Phase Eighteen State Coordinator']);
    $stateCoordinator->assignRole(PlatformRole::StateCoordinator->value);

    $localGovernmentAdmin = User::factory()->create(['name' => 'Phase Eighteen LGA Admin']);
    $localGovernmentAdmin->assignRole(PlatformRole::LocalGovernmentAdmin->value);

    $areaAgent = User::factory()->create(['name' => 'Phase Eighteen Area Agent']);
    $areaAgent->assignRole(PlatformRole::AreaAgent->value);

    $customer = User::factory()->create(['name' => 'Phase Eighteen Customer']);
    $customer->assignRole(PlatformRole::Customer->value);

    return [
        'superAdmin' => $superAdmin,
        'stateCoordinator' => $stateCoordinator,
        'localGovernmentAdmin' => $localGovernmentAdmin,
        'areaAgent' => $areaAgent,
        'customer' => $customer,
    ];
}

test('subscription plan policy and resource access are restricted to platform settings admins', function (): void {
    $users = phaseEighteenUsers();
    $unusedPlan = SubscriptionPlan::factory()->create();
    $subscribedPlan = SubscriptionPlan::factory()->create();
    $paidPlan = SubscriptionPlan::factory()->create();

    $profile = ArtisanProfile::factory()->create();
    Subscription::factory()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $subscribedPlan->id,
    ]);
    Payment::factory()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $paidPlan->id,
    ]);

    $policy = new SubscriptionPlanPolicy;

    expect($users['superAdmin']->can(PlatformPermission::ManagePlatformSettings->value))->toBeTrue()
        ->and($users['stateCoordinator']->can(PlatformPermission::ManagePlatformSettings->value))->toBeFalse()
        ->and($users['localGovernmentAdmin']->can(PlatformPermission::ManagePlatformSettings->value))->toBeFalse()
        ->and($users['areaAgent']->can(PlatformPermission::ManagePlatformSettings->value))->toBeFalse()
        ->and($users['customer']->can(PlatformPermission::ManagePlatformSettings->value))->toBeFalse()
        ->and($policy->viewAny($users['superAdmin']))->toBeTrue()
        ->and($policy->view($users['superAdmin'], $unusedPlan))->toBeTrue()
        ->and($policy->create($users['superAdmin']))->toBeTrue()
        ->and($policy->update($users['superAdmin'], $unusedPlan))->toBeTrue()
        ->and($policy->delete($users['superAdmin'], $unusedPlan))->toBeTrue()
        ->and($policy->delete($users['superAdmin'], $subscribedPlan))->toBeFalse()
        ->and($policy->delete($users['superAdmin'], $paidPlan))->toBeFalse()
        ->and($policy->restore($users['superAdmin'], $unusedPlan))->toBeFalse()
        ->and($policy->forceDelete($users['superAdmin'], $unusedPlan))->toBeFalse()
        ->and($policy->viewAny($users['stateCoordinator']))->toBeFalse()
        ->and($policy->create($users['areaAgent']))->toBeFalse()
        ->and($policy->update($users['customer'], $unusedPlan))->toBeFalse()
        ->and(SubscriptionPlanResource::getRelations())->toBe([]);

    actingAs($users['superAdmin']);
    expect(SubscriptionPlanResource::canAccess())->toBeTrue()
        ->and(SubscriptionPlanResource::getEloquentQuery()->count())->toBe(3);

    actingAs($users['stateCoordinator']);
    expect(SubscriptionPlanResource::canAccess())->toBeFalse()
        ->and(SubscriptionPlanResource::getEloquentQuery()->count())->toBe(0);

    auth()->logout();
    expect(SubscriptionPlanResource::canAccess())->toBeFalse()
        ->and(SubscriptionPlanResource::getEloquentQuery()->count())->toBe(0);
});

test('super admin can list filter and toggle activation of subscription plans with audit logs', function (): void {
    seed(SubscriptionPlanSeeder::class);
    $users = phaseEighteenUsers();

    $basicPlan = SubscriptionPlan::query()->where('slug', 'starter-listing')->firstOrFail();
    $proPlan = SubscriptionPlan::query()->where('slug', 'growth-listing')->firstOrFail();
    $premiumPlan = SubscriptionPlan::query()->where('slug', 'annual-partner')->firstOrFail();

    Livewire::actingAs($users['superAdmin']);

    Livewire::test(ListSubscriptionPlans::class)
        ->assertActionExists('create')
        ->assertCanSeeTableRecords([$basicPlan, $proPlan, $premiumPlan])
        ->assertTableColumnFormattedStateSet('price_amount', 'NGN 5,000.00', record: $basicPlan)
        ->filterTable('interval', SubscriptionInterval::Quarterly->value)
        ->assertCanSeeTableRecords([$proPlan])
        ->assertCanNotSeeTableRecords([$basicPlan, $premiumPlan])
        ->resetTableFilters()
        ->callAction(TestAction::make('toggleActive')->table($basicPlan))
        ->assertHasNoTableActionErrors();

    expect($basicPlan->refresh()->active)->toBeFalse();

    $deactivatedAudit = AuditLog::query()
        ->where('action', 'subscription_plan.deactivated')
        ->where('subject_id', $basicPlan->id)
        ->firstOrFail();

    expect($deactivatedAudit->actor_id)->toBe($users['superAdmin']->id)
        ->and($deactivatedAudit->before['active'] ?? null)->toBeTrue()
        ->and($deactivatedAudit->after['active'] ?? null)->toBeFalse();

    Livewire::test(ListSubscriptionPlans::class)
        ->callAction(TestAction::make('toggleActive')->table($basicPlan))
        ->assertHasNoTableActionErrors();

    expect($basicPlan->refresh()->active)->toBeTrue();

    $activatedAudit = AuditLog::query()
        ->where('action', 'subscription_plan.activated')
        ->where('subject_id', $basicPlan->id)
        ->firstOrFail();

    expect($activatedAudit->before['active'] ?? null)->toBeFalse()
        ->and($activatedAudit->after['active'] ?? null)->toBeTrue();
});

test('super admin can create a subscription plan and expose it on pricing', function (): void {
    seed(SubscriptionPlanSeeder::class);
    $users = phaseEighteenUsers();

    Livewire::actingAs($users['superAdmin']);

    Livewire::test(CreateSubscriptionPlan::class)
        ->fillForm([
            'name' => '',
            'slug' => 'starter-listing',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'slug' => 'unique',
        ]);

    Livewire::test(CreateSubscriptionPlan::class)
        ->fillForm([
            'name' => 'Enterprise Partner',
            'slug' => 'enterprise-partner',
            'interval' => SubscriptionInterval::Annual->value,
            'duration_days' => 365,
            'price_amount' => 8500000,
            'currency_code' => 'NGN',
            'sort_order' => 40,
            'description' => 'Multi-branch partner tier with dedicated onboarding.',
            'feature_summary' => ['Custom onboarding', 'Dedicated account desk'],
            'includes_team_management' => true,
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(SubscriptionPlan::class, [
        'name' => 'Enterprise Partner',
        'slug' => 'enterprise-partner',
        'price_amount' => 8500000,
        'duration_days' => 365,
        'includes_team_management' => true,
        'active' => true,
    ]);

    $createdPlan = SubscriptionPlan::query()->where('slug', 'enterprise-partner')->firstOrFail();
    $createdAudit = AuditLog::query()
        ->where('action', 'subscription_plan.created')
        ->where('subject_id', $createdPlan->id)
        ->firstOrFail();

    expect($createdAudit->actor_id)->toBe($users['superAdmin']->id)
        ->and($createdAudit->before)->toBeNull()
        ->and($createdAudit->after['slug'] ?? null)->toBe('enterprise-partner');

    get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Pricing')
            ->where('plans.3.name', 'Enterprise Partner')
            ->where('plans.3.slug', 'enterprise-partner')
            ->where('plans.3.description', 'Multi-branch partner tier with dedicated onboarding.'));
});

test('super admin can edit a subscription plan, is blocked from deleting referenced plans, and can delete unused plans', function (): void {
    $users = phaseEighteenUsers();

    $referencedPlan = SubscriptionPlan::factory()->create([
        'name' => 'Referenced Plan',
        'slug' => 'referenced-plan',
        'price_amount' => 500000,
        'duration_days' => 30,
        'includes_team_management' => false,
    ]);

    $profile = ArtisanProfile::factory()->create();
    Subscription::factory()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $referencedPlan->id,
    ]);

    $unusedPlan = SubscriptionPlan::factory()->create([
        'name' => 'Removable Plan',
        'slug' => 'removable-plan',
    ]);

    Livewire::actingAs($users['superAdmin']);

    Livewire::test(EditSubscriptionPlan::class, ['record' => $referencedPlan->getRouteKey()])
        ->assertActionHidden('delete')
        ->fillForm([
            'price_amount' => 750000,
            'duration_days' => 45,
            'includes_team_management' => true,
            'feature_summary' => ['Public listing', 'Priority support'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $referencedPlan->refresh();
    expect($referencedPlan->price_amount)->toBe(750000)
        ->and($referencedPlan->duration_days)->toBe(45)
        ->and($referencedPlan->includes_team_management)->toBeTrue()
        ->and($referencedPlan->feature_summary)->toBe(['Public listing', 'Priority support']);

    $updatedAudit = AuditLog::query()
        ->where('action', 'subscription_plan.updated')
        ->where('subject_id', $referencedPlan->id)
        ->firstOrFail();

    expect($updatedAudit->before['price_amount'] ?? null)->toBe(500000)
        ->and($updatedAudit->after['price_amount'] ?? null)->toBe(750000)
        ->and($updatedAudit->before['includes_team_management'] ?? null)->toBeFalse()
        ->and($updatedAudit->after['includes_team_management'] ?? null)->toBeTrue();

    Livewire::test(EditSubscriptionPlan::class, ['record' => $unusedPlan->getRouteKey()])
        ->assertActionVisible('delete')
        ->callAction(DeleteAction::class);

    assertDatabaseMissing(SubscriptionPlan::class, [
        'id' => $unusedPlan->id,
    ]);

    $deletedAudit = AuditLog::query()
        ->where('action', 'subscription_plan.deleted')
        ->where('subject_id', $unusedPlan->id)
        ->firstOrFail();

    expect($deletedAudit->before['slug'] ?? null)->toBe('removable-plan')
        ->and($deletedAudit->after)->toBeNull();
});
