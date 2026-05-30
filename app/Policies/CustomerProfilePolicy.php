<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\CustomerProfile;
use App\Models\User;

class CustomerProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ViewGlobalReports->value)
            || $user->can(PlatformPermission::ManageOwnBookings->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerProfile $customerProfile): bool
    {
        return CustomerProfile::query()
            ->whereKey($customerProfile->id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManageOwnBookings->value)
            || $user->can(PlatformPermission::ViewGlobalReports->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerProfile $customerProfile): bool
    {
        return $customerProfile->user_id === $user->id
            && $user->can(PlatformPermission::ManageOwnBookings->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerProfile $customerProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerProfile $customerProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerProfile $customerProfile): bool
    {
        return false;
    }
}
