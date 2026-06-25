<?php

namespace App\Actions\Bookings;

use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RejectBooking
{
    public function __construct(
        private readonly EnsureBookingCanBeManagedByArtisan $ensureBookingCanBeManagedByArtisan,
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Booking $booking, User $actor, ?string $notes = null): Booking
    {
        $updatedBooking = DB::transaction(function () use ($booking, $actor, $notes): Booking {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->ensureBookingCanBeManagedByArtisan->handle($lockedBooking, $actor);

            if ($lockedBooking->status !== BookingStatus::Requested) {
                throw new InvalidArgumentException('Only requested bookings can be rejected.');
            }

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'status' => BookingStatus::Rejected,
                'rejected_at' => now(),
            ])->save();
            $this->recordBookingStatus->handle($lockedBooking, $actor, $fromStatus, BookingStatus::Rejected, $notes ?? 'booking.rejected');

            return $lockedBooking->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->bookingStatusChanged($updatedBooking, BookingStatus::Rejected);

        return $updatedBooking->refresh();
    }
}
