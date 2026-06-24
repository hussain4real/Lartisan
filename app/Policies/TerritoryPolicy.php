<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\AdminProfile;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;

class TerritoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageTerritories->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Territory $territory): bool
    {
        return Territory::query()
            ->whereKey($territory)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManageTerritories->value)
            && (
                $user->can(PlatformPermission::ViewGlobalReports->value)
                || $this->activeStateScopeId($user) !== null
                || $this->activeLocalGovernmentScopeId($user) !== null
            );
    }

    public function createForLocalGovernment(User $user, LocalGovernment $localGovernment): bool
    {
        if (! $user->can(PlatformPermission::ManageTerritories->value)) {
            return false;
        }

        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return true;
        }

        $stateScopeId = $this->activeStateScopeId($user);

        if ($stateScopeId !== null) {
            return $localGovernment->state_id === $stateScopeId;
        }

        return $this->activeLocalGovernmentScopeId($user) === $localGovernment->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Territory $territory): bool
    {
        return $this->view($user, $territory)
            && $this->createForLocalGovernment($user, $territory->localGovernment()->firstOrFail());
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Territory $territory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Territory $territory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Territory $territory): bool
    {
        return false;
    }

    private function activeStateScopeId(User $user): ?int
    {
        $adminProfile = $user->adminProfile()->active()->first();

        if (! $adminProfile instanceof AdminProfile
            || $adminProfile->role !== PlatformRole::StateCoordinator
            || $adminProfile->scope_type !== (new State)->getMorphClass()
            || $adminProfile->scope_id === null) {
            return null;
        }

        return $adminProfile->scope_id;
    }

    private function activeLocalGovernmentScopeId(User $user): ?int
    {
        $adminProfile = $user->adminProfile()->active()->first();

        if (! $adminProfile instanceof AdminProfile
            || $adminProfile->role !== PlatformRole::LocalGovernmentAdmin
            || $adminProfile->scope_type !== (new LocalGovernment)->getMorphClass()
            || $adminProfile->scope_id === null) {
            return null;
        }

        return $adminProfile->scope_id;
    }
}
