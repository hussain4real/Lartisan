<?php

namespace App\Actions\Disputes;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\PlatformPermission;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Review;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpenDispute
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $evidence
     */
    public function handle(
        Booking $booking,
        User $actor,
        string $subject,
        ?string $description = null,
        DisputeSeverity $severity = DisputeSeverity::Medium,
        ?Review $review = null,
        array $evidence = [],
    ): Dispute {
        if (trim($subject) === '') {
            throw new InvalidArgumentException('A dispute subject is required.');
        }

        return DB::transaction(function () use ($booking, $actor, $subject, $description, $severity, $review, $evidence): Dispute {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $profile = $booking->artisanProfile()->firstOrFail();

            $this->authorize($actor, $booking, $profile);

            if ($review instanceof Review && $review->booking_id !== $booking->id) {
                throw new InvalidArgumentException('The selected review does not belong to this booking.');
            }

            $dispute = Dispute::query()->create([
                'booking_id' => $booking->id,
                'review_id' => $review?->id,
                'artisan_profile_id' => $profile->id,
                'customer_id' => $booking->customer_id,
                'opened_by_id' => $actor->id,
                'status' => DisputeStatus::Open,
                'severity' => $severity,
                'subject' => trim($subject),
                'description' => $description,
                'opened_at' => now(),
            ]);

            foreach ($evidence as $file) {
                $dispute->addMedia($file)->toMediaCollection(Dispute::EVIDENCE_COLLECTION);
            }

            if ($review instanceof Review) {
                $review->forceFill(['status' => ReviewStatus::Disputed])->save();
            }

            SupportCase::query()->create([
                'requester_id' => $actor->id,
                'supportable_type' => $dispute->getMorphClass(),
                'supportable_id' => $dispute->id,
                'category' => SupportCaseCategory::Dispute,
                'priority' => $this->priorityFor($severity),
                'status' => SupportCaseStatus::Open,
                'subject' => $dispute->subject,
                'description' => $dispute->description,
                'opened_at' => now(),
            ]);

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'dispute.opened',
                subject: $dispute,
                after: [
                    'status' => $dispute->status->value,
                    'severity' => $dispute->severity->value,
                    'booking_id' => $booking->id,
                ],
                reason: $description,
            );

            return $dispute->refresh();
        }, attempts: 3);
    }

    private function authorize(User $actor, Booking $booking, ArtisanProfile $profile): void
    {
        if ($booking->customer_id === $actor->id || $profile->user_id === $actor->id) {
            return;
        }

        if ($actor->can(PlatformPermission::ManageSupportCases->value)
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($actor)->exists()) {
            return;
        }

        throw new AuthorizationException('You cannot open a dispute for this booking.');
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
