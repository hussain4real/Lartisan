<?php

namespace App\Actions\Reviews;

use App\Actions\Bookings\RecordBookingStatus;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\Booking;
use App\Models\Review;
use App\Models\SupportCase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitVerifiedReview
{
    public function __construct(
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
        private readonly DetectSuspiciousReview $detectSuspiciousReview,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $proof
     */
    public function handle(
        Booking $booking,
        ?User $customer,
        int $rating,
        ?string $comment = null,
        ?string $trackerToken = null,
        array $proof = [],
    ): Review {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Review rating must be between one and five.');
        }

        $review = DB::transaction(function () use ($booking, $customer, $rating, $comment, $trackerToken, $proof): Review {
            $lockedBooking = Booking::query()
                ->with('artisanProfile')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeReviewer($lockedBooking, $customer, $trackerToken);

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

            $moderation = $this->detectSuspiciousReview->handle($lockedBooking, $rating, $comment);

            $review = Review::query()->create([
                'booking_id' => $lockedBooking->id,
                'customer_id' => $customer?->id,
                'artisan_profile_id' => $lockedBooking->artisan_profile_id,
                'rating' => $rating,
                'comment' => $comment,
                'status' => $moderation['is_suspicious'] ? ReviewStatus::PendingModeration : ReviewStatus::Published,
                'reviewed_at' => now(),
                'moderation_signal' => $moderation['signal'],
                'moderation_score' => $moderation['score'],
                'moderation_notes' => $moderation['reason'],
                'moderation_metadata' => $moderation['metadata'],
            ]);

            foreach ($proof as $file) {
                $review->addMedia($file)->toMediaCollection(Review::PROOF_COLLECTION);
            }

            if ($moderation['is_suspicious']) {
                SupportCase::query()->create([
                    'requester_id' => $customer?->id,
                    'supportable_type' => $review->getMorphClass(),
                    'supportable_id' => $review->id,
                    'category' => SupportCaseCategory::Safety,
                    'priority' => $moderation['score'] >= 75 ? SupportCasePriority::High : SupportCasePriority::Normal,
                    'status' => SupportCaseStatus::Open,
                    'subject' => 'Review moderation: '.$lockedBooking->tracker_code,
                    'description' => $moderation['reason'],
                    'opened_at' => now(),
                    'metadata' => [
                        'source' => 'review_moderation',
                        'signal' => $moderation['signal'],
                        'score' => $moderation['score'],
                    ],
                ]);
            }

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
                [
                    'review_id' => $review->id,
                    'source' => $customer instanceof User ? 'registered' : 'guest_tracker',
                ],
            );

            return $review;
        }, attempts: 3);

        $this->sendLifecycleNotification->reviewSubmitted($review);
        $this->sendLifecycleNotification->bookingStatusChanged($review->booking()->firstOrFail(), BookingStatus::Reviewed);
        $supportCase = $review->supportCases()->latest('id')->first();

        if ($supportCase instanceof SupportCase) {
            $this->sendLifecycleNotification->supportCaseOpened($supportCase);
        }

        return $review->refresh();
    }

    private function authorizeReviewer(Booking $booking, ?User $customer, ?string $trackerToken): void
    {
        if ($customer instanceof User) {
            if ($booking->customer_id === $customer->id) {
                return;
            }

            throw new AuthorizationException('Only the booking customer can review this booking.');
        }

        if ($booking->customer_id === null
            && is_string($trackerToken)
            && hash_equals($booking->secure_token_hash, hash('sha256', $trackerToken))) {
            return;
        }

        throw new AuthorizationException('Only the booking customer can review this booking.');
    }
}
