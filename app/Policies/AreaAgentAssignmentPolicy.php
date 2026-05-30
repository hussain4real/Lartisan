<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\AreaAgentAssignment;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;

class AreaAgentAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::AssignTerritories->value)
            || $user->can(PlatformPermission::SubmitFieldKyc->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AreaAgentAssignment $areaAgentAssignment): bool
    {
        return AreaAgentAssignment::query()
            ->whereKey($areaAgentAssignment->id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::AssignTerritories->value);
    }

    public function assign(User $user, Territory $territory): bool
    {
        if (! $user->can(PlatformPermission::AssignTerritories->value)) {
            return false;
        }

        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return true;
        }

        $adminProfile = $user->adminProfile()->active()->first();

        if ($adminProfile === null) {
            return false;
        }

        if ($adminProfile->scope_type === (new State)->getMorphClass() && $adminProfile->scope_id !== null) {
            return $territory->localGovernment()->where('state_id', $adminProfile->scope_id)->exists();
        }

        if ($adminProfile->scope_type === (new LocalGovernment)->getMorphClass() && $adminProfile->scope_id !== null) {
            return $territory->local_government_id === $adminProfile->scope_id;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AreaAgentAssignment $areaAgentAssignment): bool
    {
        return $this->view($user, $areaAgentAssignment)
            && $user->can(PlatformPermission::AssignTerritories->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AreaAgentAssignment $areaAgentAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AreaAgentAssignment $areaAgentAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AreaAgentAssignment $areaAgentAssignment): bool
    {
        return false;
    }
}
