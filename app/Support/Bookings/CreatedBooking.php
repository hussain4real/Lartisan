<?php

namespace App\Support\Bookings;

use App\Models\Booking;

class CreatedBooking
{
    public function __construct(
        public readonly Booking $booking,
        public readonly string $trackerToken,
    ) {}

    public function trackerUrl(): string
    {
        return route('booking-tracker.show', [
            'trackerCode' => $this->booking->tracker_code,
            'token' => $this->trackerToken,
        ]);
    }
}
