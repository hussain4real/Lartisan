<?php

namespace App\Actions\Bookings;

use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\BookingStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
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
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Booking $booking, ?User $actor = null, ?string $trackerToken = null): Booking
    {
        $confirmedBooking = DB::transaction(function () use ($booking, $actor, $trackerToken): Booking {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->authorizeCustomer($lockedBooking, $actor, $trackerToken);

            if ($lockedBooking->status !== BookingStatus::Finished) {
                throw new InvalidArgumentException('Only finished bookings can be confirmed.');
            }

            if (! $this->hasSuccessfulBookingPayment($lockedBooking)) {
                throw new InvalidArgumentException('Only paid bookings can be confirmed.');
            }

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
            ])->save();
            $this->recordBookingStatus->handle($lockedBooking, $actor, $fromStatus, BookingStatus::Confirmed, 'booking.confirmed');

            return $lockedBooking->refresh();
        }, attempts: 3);

        $this->releaseWalletBalance->handle($confirmedBooking);
        $this->sendLifecycleNotification->bookingStatusChanged($confirmedBooking, BookingStatus::Confirmed);

        return $confirmedBooking->refresh();
    }

    private function hasSuccessfulBookingPayment(Booking $booking): bool
    {
        return $booking->payments()
            ->where('purpose', PaymentPurpose::Booking->value)
            ->where('status', PaymentStatus::Successful->value)
            ->exists();
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
