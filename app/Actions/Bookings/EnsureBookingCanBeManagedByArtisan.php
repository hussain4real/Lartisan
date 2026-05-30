<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class EnsureBookingCanBeManagedByArtisan
{
    public function handle(Booking $booking, User $actor): void
    {
        if ($booking->artisanProfile()->firstOrFail()->user_id !== $actor->id) {
            throw new AuthorizationException('You cannot manage this booking.');
        }
    }
}
