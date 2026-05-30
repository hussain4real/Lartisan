<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\AdminProfile;
use App\Models\User;

class AdminProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageStateCoordinators->value)
            || $user->can(PlatformPermission::ManageLocalGovernmentAdmins->value)
            || $user->can(PlatformPermission::ManageAreaAgents->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AdminProfile $adminProfile): bool
    {
        return AdminProfile::query()
            ->whereKey($adminProfile->id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManageStateCoordinators->value)
            || $user->can(PlatformPermission::ManageLocalGovernmentAdmins->value)
            || $user->can(PlatformPermission::ManageAreaAgents->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AdminProfile $adminProfile): bool
    {
        return $this->view($user, $adminProfile)
            && (
                $user->can(PlatformPermission::ManageStateCoordinators->value)
                || $user->can(PlatformPermission::ManageLocalGovernmentAdmins->value)
                || $user->can(PlatformPermission::ManageAreaAgents->value)
            );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdminProfile $adminProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AdminProfile $adminProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AdminProfile $adminProfile): bool
    {
        return false;
    }
}
