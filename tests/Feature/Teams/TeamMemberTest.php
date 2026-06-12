<?php

use App\Enums\TeamRole;
use App\Models\User;

test('team member roles can be updated by owners', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];
    $member = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    expect($team->memberships()->where('user_id', $member->id)->firstOrFail()->role)->toBe(TeamRole::Admin);
});

test('team member roles cannot be updated by non owners', function () {
    $context = createTeamManagementContext();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = $context['team'];

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('team members can be removed by owners', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];
    $member = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.edit', $team));

    expect(User::query()->findOrFail($member->id)->belongsToTeam($team))->toBeFalse();
});

test('team members cannot be removed by non owners', function () {
    $context = createTeamManagementContext();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = $context['team'];

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertForbidden();
});

test('team owner cannot be removed', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertForbidden();

    expect(User::query()->findOrFail($owner->id)->belongsToTeam($team))->toBeTrue();
});

test('team member role cannot be set to owner', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];
    $member = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($team->memberships()->where('user_id', $member->id)->firstOrFail()->role)->toBe(TeamRole::Member);
});

test('removed member current team is set to personal team', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];
    $member = User::factory()->create();
    $personalTeam = $member->teams()->where('is_personal', true)->firstOrFail();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect(User::query()->findOrFail($member->id)->current_team_id)->toEqual($personalTeam->id);
});
