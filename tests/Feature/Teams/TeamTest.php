<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\PlatformRole;
use App\Enums\TeamKind;
use App\Enums\TeamRole;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

function teamDeletionRequest(User $user, Team $team): DeleteTeamRequest
{
    /** @var DeleteTeamRequest $request */
    $request = DeleteTeamRequest::create(route('teams.destroy', $team), 'DELETE', [
        'name' => $team->name,
    ]);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session.store'));

    $route = new Route(['DELETE'], 'settings/teams/{team}', []);
    $route->bind($request);
    $route->setParameter('team', $team);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

test('the teams index page can be rendered by pro artisans', function () {
    $context = createTeamManagementContext();

    $this
        ->actingAs($context['owner'])
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('teams/Index')
            ->where('teams.0.slug', $context['team']->slug));
});

test('customers cannot access team management', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('operations users cannot access team management', function () {
    $this->seed(PlatformAccessSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(PlatformRole::AreaAgent->value);

    $this
        ->actingAs($user)
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('basic artisans cannot access team management', function () {
    $context = createTeamManagementContext([
        'includes_team_management' => false,
    ]);

    $this
        ->actingAs($context['owner'])
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('expired subscriptions cannot access team management', function () {
    $context = createTeamManagementContext(subscriptionAttributes: [
        'ends_at' => now()->subMinute(),
    ]);

    $this
        ->actingAs($context['owner'])
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('inactive plans cannot access team management', function () {
    $context = createTeamManagementContext([
        'active' => false,
    ]);

    $this
        ->actingAs($context['owner'])
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('personal and workspace teams cannot be managed', function (TeamKind $kind) {
    $user = User::factory()->create();
    $team = $kind === TeamKind::Personal
        ? $user->teams()->where('is_personal', true)->firstOrFail()
        : Team::factory()->create(['kind' => TeamKind::Workspace]);

    if ($kind === TeamKind::Workspace) {
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    }

    $this
        ->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertForbidden();
})->with([
    'personal' => [TeamKind::Personal],
    'workspace' => [TeamKind::Workspace],
]);

test('generic teams cannot be created through team management', function () {
    $context = createTeamManagementContext();

    $this
        ->actingAs($context['owner'])
        ->post(route('teams.store'), [
            'name' => 'Test Team',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('teams', [
        'name' => 'Test Team',
        'kind' => TeamKind::Workspace->value,
    ]);
});

test('team store internals create a team when explicitly authorized', function () {
    $user = User::factory()->create();
    /** @var SaveTeamRequest $request */
    $request = SaveTeamRequest::create(route('teams.store'), 'POST', [
        'name' => 'Internal Team',
    ]);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session.store'));

    Gate::shouldReceive('authorize')
        ->once()
        ->with('create', Team::class)
        ->andReturnNull();

    $response = app(TeamController::class)->store($request, app(CreateTeam::class));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    $this->assertDatabaseHas('teams', [
        'name' => 'Internal Team',
        'kind' => TeamKind::Workspace->value,
    ]);
});

test('team slug uses next available suffix', function () {
    $user = User::factory()->create();

    Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    app(CreateTeam::class)->handle($user, 'Acme');

    $this->assertDatabaseHas('teams', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('the team edit page can be rendered for eligible artisan teams', function () {
    $context = createTeamManagementContext();

    $this
        ->actingAs($context['owner'])
        ->get(route('teams.edit', $context['team']))
        ->assertOk();
});

test('teams can be updated by eligible owners', function () {
    $context = createTeamManagementContext();
    $team = $context['team'];

    $response = $this
        ->actingAs($context['owner'])
        ->patch(route('teams.update', $team), [
            'name' => 'Updated Name',
        ]);

    $response->assertRedirect(route('teams.edit', $team->fresh()));

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Name',
    ]);
});

test('teams cannot be updated by members', function () {
    $context = createTeamManagementContext();
    $member = User::factory()->create();
    $team = $context['team'];

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($member)
        ->patch(route('teams.update', $team), [
            'name' => 'Updated Name',
        ])
        ->assertForbidden();
});

test('artisan business team owners can delete teams through team management', function () {
    $context = createTeamManagementContext();
    $team = $context['team'];

    $this
        ->actingAs($context['owner'])
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ])
        ->assertRedirect(route('teams.index'));

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
});

test('team deletion stays forbidden even with wrong confirmation', function () {
    $context = createTeamManagementContext();
    $team = $context['team'];

    $response = $this
        ->actingAs($context['owner'])
        ->delete(route('teams.destroy', $team), [
            'name' => 'Wrong Name',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'deleted_at' => null,
    ]);
});

test('delete team request exposes authorization rules and confirmation errors', function () {
    $context = createTeamManagementContext();
    $team = $context['team'];

    $this->actingAs($context['owner']);

    $request = teamDeletionRequest($context['owner'], $team);

    expect($request->authorize())->toBeTrue();
    expect($request->rules())->toHaveKey('name');

    $request->merge(['name' => 'Wrong Name']);
    $validator = Validator::make(['name' => 'Wrong Name'], $request->rules());

    foreach ($request->after() as $callback) {
        $callback($validator);
    }

    expect($validator->errors()->has('name'))->toBeTrue();
});

test('deleting current team switches to alphabetically first remaining team internally', function () {
    $context = createTeamManagementContext();
    $user = $context['owner'];
    $zuluTeam = $context['team'];
    $personalTeam = $user->teams()->where('is_personal', true)->firstOrFail();

    $personalTeam->forceFill(['name' => 'Zulu Personal Team'])->save();

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->switchTeam($zuluTeam);

    $response = app(TeamController::class)->destroy(teamDeletionRequest($user, $zuluTeam), $zuluTeam);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    $this->assertSoftDeleted('teams', [
        'id' => $zuluTeam->id,
    ]);
    expect(User::query()->findOrFail($user->id)->current_team_id)->toEqual($alphaTeam->id);
});

test('deleting current team falls back to personal team when alphabetically first internally', function () {
    $context = createTeamManagementContext();
    $user = $context['owner'];
    $personalTeam = $user->teams()->where('is_personal', true)->firstOrFail();
    $team = $context['team'];

    $user->switchTeam($team);

    app(TeamController::class)->destroy(teamDeletionRequest($user, $team), $team);

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
    expect(User::query()->findOrFail($user->id)->current_team_id)->toEqual($personalTeam->id);
});

test('deleting non current team leaves current team unchanged internally', function () {
    $context = createTeamManagementContext();
    $user = $context['owner'];
    $personalTeam = $user->teams()->where('is_personal', true)->firstOrFail();
    $team = $context['team'];

    $user->switchTeam($personalTeam);

    app(TeamController::class)->destroy(teamDeletionRequest($user, $team), $team);

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
    expect(User::query()->findOrFail($user->id)->current_team_id)->toEqual($personalTeam->id);
});

test('deleting team switches other affected users to their personal team internally', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $member = User::factory()->create();
    $team = $context['team'];

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);
    $member->switchTeam($team);

    app(TeamController::class)->destroy(teamDeletionRequest($owner, $team), $team);

    $memberPersonalTeam = $member->teams()->where('is_personal', true)->firstOrFail();

    expect(User::query()->findOrFail($member->id)->current_team_id)->toEqual($memberPersonalTeam->id);
});

test('personal teams cannot be deleted', function () {
    $user = User::factory()->create();
    $personalTeam = $user->teams()->where('is_personal', true)->firstOrFail();

    $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $personalTeam), [
            'name' => $personalTeam->name,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('teams', [
        'id' => $personalTeam->id,
        'deleted_at' => null,
    ]);
});

test('users can switch teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('teams.switch', $team));

    $response->assertRedirect();

    expect(User::query()->findOrFail($user->id)->current_team_id)->toEqual($team->id);
});

test('users cannot switch to team they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('teams.switch', $team))
        ->assertForbidden();
});

test('guests cannot access teams', function () {
    $this
        ->get(route('teams.index'))
        ->assertRedirect(route('login'));
});
