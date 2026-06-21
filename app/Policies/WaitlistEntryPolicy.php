<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\User;
use App\Models\WaitlistEntry;

class WaitlistEntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ViewWaitlistEntries->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WaitlistEntry $waitlistEntry): bool
    {
        return $this->viewAny($user)
            && WaitlistEntry::query()
                ->whereKey($waitlistEntry)
                ->visibleTo($user)
                ->exists();
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
    public function update(User $user, WaitlistEntry $waitlistEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WaitlistEntry $waitlistEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WaitlistEntry $waitlistEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WaitlistEntry $waitlistEntry): bool
    {
        return false;
    }
}
