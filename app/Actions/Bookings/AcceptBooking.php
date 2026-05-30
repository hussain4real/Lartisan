<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AcceptBooking
{
    public function __construct(
        private readonly EnsureBookingCanBeManagedByArtisan $ensureBookingCanBeManagedByArtisan,
        private readonly RecordBookingStatus $recordBookingStatus,
    ) {}

    public function handle(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor): Booking {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->ensureBookingCanBeManagedByArtisan->handle($lockedBooking, $actor);

            if ($lockedBooking->status !== BookingStatus::Requested) {
                throw new InvalidArgumentException('Only requested bookings can be accepted.');
            }

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'status' => BookingStatus::Accepted,
                'accepted_at' => now(),
            ])->save();
            $this->recordBookingStatus->handle($lockedBooking, $actor, $fromStatus, BookingStatus::Accepted, 'booking.accepted');

            return $lockedBooking->refresh();
        }, attempts: 3);
    }
}
