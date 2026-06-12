<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('team invitations can be created', function () {
    Notification::fake();

    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];

    $response = $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'role' => TeamRole::Member->value,
    ]);
});

test('team invitations can be created by admins', function () {
    Notification::fake();

    $context = createTeamManagementContext();
    $admin = User::factory()->create();
    $team = $context['team'];

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $response = $this
        ->actingAs($admin)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));
});

test('existing team members cannot be invited', function () {
    Notification::fake();

    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $member = User::factory()->create(['email' => 'member@example.com']);
    $team = $context['team'];

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'member@example.com',
            'role' => TeamRole::Member->value,
        ]);

    $response->assertSessionHasErrors('email');
});

test('duplicate invitations cannot be created', function () {
    Notification::fake();

    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];

    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ]);

    $response->assertSessionHasErrors('email');
});

test('team invitations cannot be created by members', function () {
    $context = createTeamManagementContext();
    $member = User::factory()->create();
    $team = $context['team'];

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ]);

    $response->assertForbidden();
});

test('team invitations can be cancelled by owners', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $team = $context['team'];

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.invitations.destroy', [$team, $invitation]));

    $response->assertRedirect(route('teams.edit', $team));

    $this->assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('team invitations can be accepted', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = $context['team'];

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'role' => TeamRole::Member,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.accept', $invitation));

    $response->assertRedirect(route('dashboard'));

    expect(User::query()->findOrFail($invitedUser->id)->belongsToTeam($team))->toBeTrue();
    expect(TeamInvitation::query()->findOrFail($invitation->id)->accepted_at)->not->toBeNull();
});

test('team invitations cannot be accepted by uninvited user', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
    $team = $context['team'];

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($uninvitedUser)
        ->get(route('invitations.accept', $invitation));

    $response->assertSessionHasErrors('invitation');

    expect(User::query()->findOrFail($uninvitedUser->id)->belongsToTeam($team))->toBeFalse();
});

test('expired invitations cannot be accepted', function () {
    $context = createTeamManagementContext();
    $owner = $context['owner'];
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = $context['team'];

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.accept', $invitation));

    $response->assertSessionHasErrors('invitation');

    expect(User::query()->findOrFail($invitedUser->id)->belongsToTeam($team))->toBeFalse();
});
