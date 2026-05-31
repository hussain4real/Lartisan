<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\Dispute;
use App\Models\User;

class DisputePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageSupportCases->value)
            || $user->can(PlatformPermission::ViewScopedBookings->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Dispute $dispute): bool
    {
        $profile = $dispute->artisanProfile()->first();

        return $profile instanceof ArtisanProfile
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($user)->exists()
            && $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManageSupportCases->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Dispute $dispute): bool
    {
        return $user->can(PlatformPermission::ManageSupportCases->value)
            && $this->view($user, $dispute);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Dispute $dispute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Dispute $dispute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Dispute $dispute): bool
    {
        return false;
    }
}
