<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;

class RecordBookingStatus
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Booking $booking,
        ?User $actor,
        ?BookingStatus $fromStatus,
        BookingStatus $toStatus,
        ?string $notes = null,
        ?array $metadata = null,
    ): BookingStatusHistory {
        return BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'actor_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
