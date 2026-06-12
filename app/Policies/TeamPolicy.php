<?php

namespace App\Policies;

use App\Enums\SubscriptionStatus;
use App\Enums\TeamKind;
use App\Enums\TeamPermission;
use App\Models\ArtisanProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $now = now();

        return $user->teams()
            ->where('kind', TeamKind::ArtisanBusiness)
            ->whereHas('artisanProfile.subscriptions', function (Builder $query) use ($now): void {
                $query
                    ->where('status', SubscriptionStatus::Active->value)
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>', $now);
                    })
                    ->whereHas('plan', function (Builder $query): void {
                        $query
                            ->where('active', true)
                            ->where('includes_team_management', true);
                    });
            })
            ->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team)
            && $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team)
            && $user->hasTeamPermission($team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the team.
     */
    public function updateMember(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team)
            && $user->hasTeamPermission($team, TeamPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team)
            && $user->hasTeamPermission($team, TeamPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team)
            && $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $this->canAccessTeamManagement($user, $team)
            && $user->hasTeamPermission($team, TeamPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return false;
    }

    private function canAccessTeamManagement(User $user, Team $team): bool
    {
        if ($team->kind !== TeamKind::ArtisanBusiness || ! $user->belongsToTeam($team)) {
            return false;
        }

        $profile = $team->artisanProfile()->first();

        return $profile instanceof ArtisanProfile
            && $profile->hasActiveTeamManagementSubscription();
    }
}
