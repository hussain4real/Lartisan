<?php

namespace App\Policies;

use App\Models\ArtisanProfile;
use App\Models\BookingMessage;
use App\Models\User;

class BookingMessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BookingMessage $bookingMessage): bool
    {
        $booking = $bookingMessage->booking()->first();

        if ($booking === null) {
            return false;
        }

        if ($booking->customer_id === $user->id) {
            return true;
        }

        $profile = $booking->artisanProfile()->first();

        return $profile instanceof ArtisanProfile && $profile->user_id === $user->id;
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
    public function update(User $user, BookingMessage $bookingMessage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BookingMessage $bookingMessage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BookingMessage $bookingMessage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BookingMessage $bookingMessage): bool
    {
        return false;
    }
}
