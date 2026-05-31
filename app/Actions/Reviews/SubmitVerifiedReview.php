<?php

namespace App\Actions\Reviews;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitVerifiedReview
{
    public function handle(Booking $booking, User $customer, int $rating, ?string $comment = null): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Review rating must be between one and five.');
        }

        return DB::transaction(function () use ($booking, $customer, $rating, $comment): Review {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->customer_id !== $customer->id) {
                throw new AuthorizationException('Only the booking customer can review this booking.');
            }

            if ($lockedBooking->status !== BookingStatus::Confirmed || $lockedBooking->wallet_released_at === null) {
                throw new InvalidArgumentException('Only confirmed paid bookings can be reviewed.');
            }

            $hasBookingCredit = WalletLedgerEntry::query()
                ->where('source_type', $lockedBooking->getMorphClass())
                ->where('source_id', $lockedBooking->id)
                ->where('type', WalletLedgerEntryType::BookingCredit)
                ->exists();

            if (! $hasBookingCredit) {
                throw new InvalidArgumentException('Only bookings with released wallet credit can be reviewed.');
            }

            if ($lockedBooking->review()->exists()) {
                throw new InvalidArgumentException('This booking has already been reviewed.');
            }

            return Review::query()->create([
                'booking_id' => $lockedBooking->id,
                'customer_id' => $customer->id,
                'artisan_profile_id' => $lockedBooking->artisan_profile_id,
                'rating' => $rating,
                'comment' => $comment,
                'status' => ReviewStatus::Published,
                'reviewed_at' => now(),
            ]);
        }, attempts: 3);
    }
}
