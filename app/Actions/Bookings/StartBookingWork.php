<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartBookingWork
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

            if ($lockedBooking->status !== BookingStatus::Accepted) {
                throw new InvalidArgumentException('Only accepted bookings can be started.');
            }

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'status' => BookingStatus::InProgress,
                'started_at' => now(),
            ])->save();
            $this->recordBookingStatus->handle($lockedBooking, $actor, $fromStatus, BookingStatus::InProgress, 'booking.started');

            return $lockedBooking->refresh();
        }, attempts: 3);
    }
}
