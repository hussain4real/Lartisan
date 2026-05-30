<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmBookingCompletion
{
    public function __construct(
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly ReleaseWalletBalance $releaseWalletBalance,
    ) {}

    public function handle(Booking $booking, ?User $actor = null, ?string $trackerToken = null): Booking
    {
        $confirmedBooking = DB::transaction(function () use ($booking, $actor, $trackerToken): Booking {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->authorizeCustomer($lockedBooking, $actor, $trackerToken);

            if ($lockedBooking->status !== BookingStatus::Finished) {
                throw new InvalidArgumentException('Only finished bookings can be confirmed.');
            }

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
            ])->save();
            $this->recordBookingStatus->handle($lockedBooking, $actor, $fromStatus, BookingStatus::Confirmed, 'booking.confirmed');

            return $lockedBooking->refresh();
        }, attempts: 3);

        if ($confirmedBooking->quoted_amount !== null && $confirmedBooking->quoted_amount > 0) {
            $this->releaseWalletBalance->handle($confirmedBooking);
        }

        return $confirmedBooking->refresh();
    }

    private function authorizeCustomer(Booking $booking, ?User $actor, ?string $trackerToken): void
    {
        $hasCustomerAccess = $actor instanceof User && $booking->customer_id === $actor->id;
        $hasTrackerAccess = $trackerToken !== null
            && hash_equals($booking->secure_token_hash, hash('sha256', $trackerToken));

        if (! $hasCustomerAccess && ! $hasTrackerAccess) {
            throw new AuthorizationException('You cannot confirm this booking.');
        }
    }
}
