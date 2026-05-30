<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\User;

class ArtisanServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageOwnServices->value)
            || $user->can(PlatformPermission::ModerateArtisanProfiles->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ArtisanService $artisanService): bool
    {
        $profile = $artisanService->artisanProfile()->firstOrFail();

        return app(ArtisanProfilePolicy::class)->view($user, $profile);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ArtisanProfile $artisanProfile): bool
    {
        if ($artisanProfile->user_id === $user->id) {
            return $user->can(PlatformPermission::ManageOwnServices->value);
        }

        return $user->can(PlatformPermission::ModerateArtisanProfiles->value)
            && app(ArtisanProfilePolicy::class)->view($user, $artisanProfile);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ArtisanService $artisanService): bool
    {
        $profile = $artisanService->artisanProfile()->firstOrFail();

        if ($profile->user_id === $user->id) {
            return $user->can(PlatformPermission::ManageOwnServices->value);
        }

        return $user->can(PlatformPermission::ModerateArtisanProfiles->value)
            && app(ArtisanProfilePolicy::class)->view($user, $profile);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ArtisanService $artisanService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ArtisanService $artisanService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ArtisanService $artisanService): bool
    {
        return false;
    }
}
