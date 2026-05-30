<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ReasonCode;
use App\Models\User;

class ReasonCodePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ReviewStandardKyc->value)
            || $user->can(PlatformPermission::ReviewEscalatedKyc->value)
            || $user->can(PlatformPermission::AssignTerritories->value)
            || $user->can(PlatformPermission::ModerateArtisanProfiles->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReasonCode $reasonCode): bool
    {
        return $reasonCode->active && $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReasonCode $reasonCode): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReasonCode $reasonCode): bool
    {
        return $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReasonCode $reasonCode): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReasonCode $reasonCode): bool
    {
        return false;
    }
}
