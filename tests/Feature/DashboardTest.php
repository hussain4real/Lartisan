<?php

use App\Models\User;
use Database\Seeders\PilotUserSeeder;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('auth.operationPanel', null)
            ->where('currentTeam.kind', 'personal'));
});

test('guests can visit the public marketplace without authenticated context', function () {
    $this->seed(PilotUserSeeder::class);

    $this
        ->get(route('marketplace.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('marketplace/Index')
            ->where('auth.user', null)
            ->where('auth.operationPanel', null)
            ->where('currentTeam', null)
            ->where('teams', []));
});

test('pilot actor dashboards expose profile aware navigation context', function () {
    $this->seed(PilotUserSeeder::class);

    /** @var list<array{email: string, team_kind: string, operation_panel: array{id: string, title: string}|null}> $expectations */
    $expectations = [
        [
            'email' => 'super.admin@lartisan.test',
            'team_kind' => 'personal',
            'operation_panel' => ['id' => 'admin', 'title' => 'Admin panel'],
        ],
        [
            'email' => 'state.coordinator@lartisan.test',
            'team_kind' => 'personal',
            'operation_panel' => ['id' => 'state', 'title' => 'State panel'],
        ],
        [
            'email' => 'lga.admin@lartisan.test',
            'team_kind' => 'personal',
            'operation_panel' => ['id' => 'lga', 'title' => 'LGA panel'],
        ],
        [
            'email' => 'area.agent@lartisan.test',
            'team_kind' => 'personal',
            'operation_panel' => ['id' => 'agent', 'title' => 'Agent panel'],
        ],
        [
            'email' => 'artisan@lartisan.test',
            'team_kind' => 'artisan-business',
            'operation_panel' => null,
        ],
        [
            'email' => 'customer@lartisan.test',
            'team_kind' => 'personal',
            'operation_panel' => null,
        ],
    ];

    foreach ($expectations as $expectation) {
        $user = User::query()->where('email', $expectation['email'])->firstOrFail();
        $currentTeam = $user->currentTeam()->firstOrFail();

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['current_team' => $currentTeam]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Dashboard')
                ->where('auth.operationPanel', $expectation['operation_panel'])
                ->where('currentTeam.kind', $expectation['team_kind']));
    }
});

test('pilot actor navigation targets resolve for their profile type', function () {
    $this->seed(PilotUserSeeder::class);

    /** @var array<string, array{panel: string, route: string}> $operationPanelRoutes */
    $operationPanelRoutes = [
        'super.admin@lartisan.test' => ['panel' => 'admin', 'route' => 'filament.admin.pages.dashboard'],
        'state.coordinator@lartisan.test' => ['panel' => 'state', 'route' => 'filament.state.pages.dashboard'],
        'lga.admin@lartisan.test' => ['panel' => 'lga', 'route' => 'filament.lga.pages.dashboard'],
        'area.agent@lartisan.test' => ['panel' => 'agent', 'route' => 'filament.agent.pages.dashboard'],
    ];

    foreach ($operationPanelRoutes as $email => $panelRoute) {
        $user = User::query()->where('email', $email)->firstOrFail();

        Filament::setCurrentPanel($panelRoute['panel']);
        session()->flush();

        $this->actingAs($user)->get(route($panelRoute['route']))->assertOk();
    }

    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();
    $customerTeam = $customer->currentTeam()->firstOrFail();

    session()->flush();

    $this->actingAs($customer)->get(route('marketplace.index'))->assertOk();
    $this->actingAs($customer)->get(route('customer.bookings.index'))->assertOk();
    $this->actingAs($customer)->get(route('artisan.onboarding.create', ['current_team' => $customerTeam]))->assertOk();
    $this->actingAs($customer)->get(route('identity.phone.edit'))->assertOk();

    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();
    $artisanTeam = $artisan->currentTeam()->firstOrFail();

    /** @var list<string> $artisanRoutes */
    $artisanRoutes = [
        'dashboard',
        'artisan.dashboard',
        'artisan.profile.edit',
        'artisan.services.index',
        'artisan.bookings.index',
        'artisan.kyc.show',
        'artisan.subscription.show',
        'artisan.wallet.show',
        'artisan.onboarding.create',
    ];

    foreach ($artisanRoutes as $routeName) {
        session()->flush();

        $this
            ->actingAs($artisan)
            ->get(route($routeName, ['current_team' => $artisanTeam]))
            ->assertOk();
    }
});
