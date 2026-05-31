<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ViewPayments->value)
            || $user->can(PlatformPermission::ManagePayouts->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payout $payout): bool
    {
        $profile = $payout->artisanProfile()->first();

        return $profile instanceof ArtisanProfile
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($user)->exists()
            && $this->viewAny($user);
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
    public function update(User $user, Payout $payout): bool
    {
        return $user->can(PlatformPermission::ManagePayouts->value)
            && $this->view($user, $payout);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payout $payout): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payout $payout): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payout $payout): bool
    {
        return false;
    }
}
