<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display a listing of the user's teams.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('teams/Index', [
            'teams' => $user->toUserTeams(includeCurrent: true),
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $team = $createTeam->handle($user, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
            ],
            'members' => $team->members()->get()
                ->map(fn (User $member): ?array => $this->memberPayload($member))
                ->filter()
                ->values(),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn (TeamInvitation $invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at?->toISOString(),
                ]),
            'permissions' => $user->toTeamPermissions($team),
            'availableRoles' => TeamRole::assignable(),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $request->string('name')->toString()]);

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->belongsToTeam($team)) {
            abort(403);
        }

        $user->switchTeam($team);

        return back();
    }

    /**
     * Delete the specified team.
     */
    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team) {
            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(function (User $affectedUser): void {
                    $personalTeam = $affectedUser->personalTeam();

                    if ($personalTeam instanceof Team) {
                        $affectedUser->switchTeam($personalTeam);
                    }
                });

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('teams.index');
    }

    /**
     * @return array{id: int, name: string, email: string, avatar: string|null, role: string, role_label: string}|null
     */
    protected function memberPayload(User $member): ?array
    {
        $membership = $member->pivot;

        if (! $membership instanceof Membership) {
            return null;
        }

        $avatar = $member->getAttribute('avatar');
        $avatar = is_string($avatar) ? $avatar : null;

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $avatar,
            'role' => $membership->role->value,
            'role_label' => $membership->role->label(),
        ];
    }
}
