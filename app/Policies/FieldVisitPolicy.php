<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\FieldVisit;
use App\Models\User;

class FieldVisitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::SubmitFieldKyc->value)
            || $user->can(PlatformPermission::ModerateArtisanProfiles->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FieldVisit $fieldVisit): bool
    {
        $profile = $fieldVisit->artisanProfile()->firstOrFail();

        return app(ArtisanProfilePolicy::class)->view($user, $profile);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ArtisanProfile $artisanProfile): bool
    {
        return $user->can(PlatformPermission::SubmitFieldKyc->value)
            && app(ArtisanProfilePolicy::class)->view($user, $artisanProfile);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FieldVisit $fieldVisit): bool
    {
        return $this->view($user, $fieldVisit)
            && $user->can(PlatformPermission::SubmitFieldKyc->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FieldVisit $fieldVisit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FieldVisit $fieldVisit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FieldVisit $fieldVisit): bool
    {
        return false;
    }
}
