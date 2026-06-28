<?php

namespace App\Actions\Disputes;

use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\DisputeTargetType;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SupportCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpenGuestDispute
{
    public function __construct(private readonly SendLifecycleNotification $sendLifecycleNotification) {}

    /**
     * @param  array<int, UploadedFile>  $evidence
     */
    public function handle(
        Booking $booking,
        string $trackerToken,
        string $subject,
        ?string $description = null,
        DisputeSeverity $severity = DisputeSeverity::Medium,
        ?Review $review = null,
        array $evidence = [],
        ?Payment $payment = null,
        DisputeTargetType $target = DisputeTargetType::Booking,
    ): Dispute {
        if (trim($subject) === '') {
            throw new InvalidArgumentException('A dispute subject is required.');
        }

        $dispute = DB::transaction(function () use ($booking, $trackerToken, $subject, $description, $severity, $review, $evidence, $payment, $target): Dispute {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->customer_id !== null
                || ! hash_equals($booking->secure_token_hash, hash('sha256', $trackerToken))) {
                throw new AuthorizationException('You cannot open a dispute for this booking.');
            }

            if ($review instanceof Review && $review->booking_id !== $booking->id) {
                throw new InvalidArgumentException('The selected review does not belong to this booking.');
            }

            if ($payment instanceof Payment && $payment->booking_id !== $booking->id) {
                throw new InvalidArgumentException('The selected payment does not belong to this booking.');
            }

            $target = match (true) {
                $review instanceof Review => DisputeTargetType::Review,
                $payment instanceof Payment => DisputeTargetType::Payment,
                default => $target,
            };

            $dispute = Dispute::query()->create([
                'booking_id' => $booking->id,
                'review_id' => $review?->id,
                'payment_id' => $payment?->id,
                'artisan_profile_id' => $booking->artisan_profile_id,
                'customer_id' => null,
                'opened_by_id' => null,
                'status' => DisputeStatus::Open,
                'severity' => $severity,
                'target' => $target,
                'subject' => trim($subject),
                'description' => $description,
                'opened_at' => now(),
                'metadata' => [
                    'source' => 'guest_tracker',
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'customer_email' => $booking->customer_email,
                    'target' => $target->value,
                    'payment_reference' => $payment?->reference,
                ],
            ]);

            foreach ($evidence as $file) {
                $dispute->addMedia($file)->toMediaCollection(Dispute::EVIDENCE_COLLECTION);
            }

            if ($review instanceof Review) {
                $review->forceFill(['status' => ReviewStatus::Disputed])->save();
            }

            SupportCase::query()->create([
                'requester_id' => null,
                'supportable_type' => $dispute->getMorphClass(),
                'supportable_id' => $dispute->id,
                'category' => SupportCaseCategory::Dispute,
                'priority' => $this->priorityFor($severity),
                'status' => SupportCaseStatus::Open,
                'subject' => $dispute->subject,
                'description' => $dispute->description,
                'opened_at' => now(),
                'metadata' => ['source' => 'guest_tracker'],
            ]);

            return $dispute->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->disputeOpened($dispute);
        $supportCase = $dispute->supportCases()->latest('id')->first();

        if ($supportCase instanceof SupportCase) {
            $this->sendLifecycleNotification->supportCaseOpened($supportCase);
        }

        return $dispute->refresh();
    }

    private function priorityFor(DisputeSeverity $severity): SupportCasePriority
    {
        return match ($severity) {
            DisputeSeverity::Critical => SupportCasePriority::Urgent,
            DisputeSeverity::High => SupportCasePriority::High,
            DisputeSeverity::Medium => SupportCasePriority::Normal,
            DisputeSeverity::Low => SupportCasePriority::Low,
        };
    }
}
