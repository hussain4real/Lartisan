<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\User;

class ArtisanProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ModerateArtisanProfiles->value)
            || $user->can(PlatformPermission::ManageOwnArtisanProfile->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ArtisanProfile $artisanProfile): bool
    {
        return ArtisanProfile::query()
            ->whereKey($artisanProfile->id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PlatformPermission::ManageOwnArtisanProfile->value)
            || $user->can(PlatformPermission::ModerateArtisanProfiles->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ArtisanProfile $artisanProfile): bool
    {
        if ($artisanProfile->user_id === $user->id) {
            return $user->can(PlatformPermission::ManageOwnArtisanProfile->value);
        }

        return $user->can(PlatformPermission::ModerateArtisanProfiles->value)
            && $this->view($user, $artisanProfile);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ArtisanProfile $artisanProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ArtisanProfile $artisanProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ArtisanProfile $artisanProfile): bool
    {
        return false;
    }
}
