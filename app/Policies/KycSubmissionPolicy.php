<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\KycSubmission;
use App\Models\User;

class KycSubmissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageOwnArtisanProfile->value)
            || $user->can(PlatformPermission::SubmitFieldKyc->value)
            || $user->can(PlatformPermission::ReviewStandardKyc->value)
            || $user->can(PlatformPermission::ReviewEscalatedKyc->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, KycSubmission $kycSubmission): bool
    {
        $profile = $kycSubmission->artisanProfile()->firstOrFail();

        return app(ArtisanProfilePolicy::class)->view($user, $profile);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ArtisanProfile $artisanProfile): bool
    {
        if ($artisanProfile->user_id === $user->id) {
            return $user->can(PlatformPermission::ManageOwnArtisanProfile->value);
        }

        return $user->can(PlatformPermission::SubmitFieldKyc->value)
            && app(ArtisanProfilePolicy::class)->view($user, $artisanProfile);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, KycSubmission $kycSubmission): bool
    {
        $profile = $kycSubmission->artisanProfile()->firstOrFail();

        if ($profile->user_id === $user->id) {
            return $user->can(PlatformPermission::ManageOwnArtisanProfile->value);
        }

        return ($user->can(PlatformPermission::SubmitFieldKyc->value)
            || $user->can(PlatformPermission::ReviewStandardKyc->value)
            || $user->can(PlatformPermission::ReviewEscalatedKyc->value))
            && app(ArtisanProfilePolicy::class)->view($user, $profile);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, KycSubmission $kycSubmission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, KycSubmission $kycSubmission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, KycSubmission $kycSubmission): bool
    {
        return false;
    }
}
