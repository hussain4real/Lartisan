<?php

namespace App\Actions\Reviews;

use App\Actions\Bookings\RecordBookingStatus;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Enums\WalletLedgerDirection;
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
    public function __construct(
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Booking $booking, User $customer, int $rating, ?string $comment = null): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Review rating must be between one and five.');
        }

        $review = DB::transaction(function () use ($booking, $customer, $rating, $comment): Review {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->customer_id !== $customer->id) {
                throw new AuthorizationException('Only the booking customer can review this booking.');
            }

            if ($lockedBooking->review()->exists()) {
                throw new InvalidArgumentException('This booking has already been reviewed.');
            }

            if ($lockedBooking->status !== BookingStatus::Settled || $lockedBooking->wallet_released_at === null) {
                throw new InvalidArgumentException('Only settled paid bookings can be reviewed.');
            }

            $hasSettlementCredit = WalletLedgerEntry::query()
                ->where('source_type', $lockedBooking->getMorphClass())
                ->where('source_id', $lockedBooking->id)
                ->where('type', WalletLedgerEntryType::SettlementCredit)
                ->where('direction', WalletLedgerDirection::Credit)
                ->exists();

            if (! $hasSettlementCredit) {
                throw new InvalidArgumentException('Only bookings with released settlement credit can be reviewed.');
            }

            $review = Review::query()->create([
                'booking_id' => $lockedBooking->id,
                'customer_id' => $customer->id,
                'artisan_profile_id' => $lockedBooking->artisan_profile_id,
                'rating' => $rating,
                'comment' => $comment,
                'status' => ReviewStatus::Published,
                'reviewed_at' => now(),
            ]);

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'reviewed_at' => now(),
                'status' => BookingStatus::Reviewed,
            ])->save();
            $this->recordBookingStatus->handle(
                $lockedBooking,
                $customer,
                $fromStatus,
                BookingStatus::Reviewed,
                'booking.reviewed',
                ['review_id' => $review->id],
            );

            return $review;
        }, attempts: 3);

        $this->sendLifecycleNotification->reviewSubmitted($review);
        $this->sendLifecycleNotification->bookingStatusChanged($review->booking()->firstOrFail(), BookingStatus::Reviewed);

        return $review->refresh();
    }
}
