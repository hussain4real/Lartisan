<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PlatformPermission::ManageSupportCases->value);
    }

    public function view(User $user, Review $review): bool
    {
        $profile = $review->artisanProfile()->first();

        return $profile instanceof ArtisanProfile
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($user)->exists()
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Review $review): bool
    {
        return $this->view($user, $review);
    }

    public function delete(User $user, Review $review): bool
    {
        return false;
    }

    public function restore(User $user, Review $review): bool
    {
        return false;
    }

    public function forceDelete(User $user, Review $review): bool
    {
        return false;
    }
}
