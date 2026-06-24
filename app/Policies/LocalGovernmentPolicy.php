<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\AdminProfile;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\User;

class LocalGovernmentPolicy
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
    public function view(User $user, LocalGovernment $localGovernment): bool
    {
        return LocalGovernment::query()
            ->whereKey($localGovernment)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManageTerritories->value)
            && ($user->can(PlatformPermission::ViewGlobalReports->value) || $this->activeStateScopeId($user) !== null);
    }

    public function createForState(User $user, State $state): bool
    {
        if (! $user->can(PlatformPermission::ManageTerritories->value)) {
            return false;
        }

        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return true;
        }

        return $this->activeStateScopeId($user) === $state->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LocalGovernment $localGovernment): bool
    {
        return $this->view($user, $localGovernment)
            && $this->createForState($user, $localGovernment->state()->firstOrFail());
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LocalGovernment $localGovernment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LocalGovernment $localGovernment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LocalGovernment $localGovernment): bool
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
}
