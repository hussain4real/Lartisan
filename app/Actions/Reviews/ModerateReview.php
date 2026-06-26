<?php

namespace App\Actions\Reviews;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PlatformPermission;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseStatus;
use App\Models\ArtisanProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ModerateReview
{
    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}

    public function handle(Review $review, User $actor, ReviewStatus $status, string $notes): Review
    {
        $notes = trim($notes);

        if ($notes === '') {
            throw new InvalidArgumentException('Moderation notes are required.');
        }

        if (! in_array($status, [ReviewStatus::Published, ReviewStatus::Hidden], true)) {
            throw new InvalidArgumentException('Reviews can only be approved or hidden by this moderation action.');
        }

        return DB::transaction(function () use ($review, $actor, $status, $notes): Review {
            $lockedReview = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $lockedReview);

            $before = [
                'status' => $lockedReview->status->value,
                'moderation_notes' => $lockedReview->moderation_notes,
            ];

            $lockedReview->forceFill([
                'moderated_by' => $actor->id,
                'moderated_at' => now(),
                'moderation_notes' => $notes,
                'status' => $status,
            ])->save();

            $lockedReview->supportCases()->update([
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'status' => SupportCaseStatus::Resolved->value,
            ]);

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'review.moderated',
                subject: $lockedReview,
                before: $before,
                after: [
                    'status' => $status->value,
                    'moderation_notes' => $notes,
                ],
                reason: $notes,
            );

            return $lockedReview->refresh();
        }, attempts: 3);
    }

    private function authorize(User $actor, Review $review): void
    {
        $profile = $review->artisanProfile()->first();

        if ($profile instanceof ArtisanProfile
            && $actor->can(PlatformPermission::ManageSupportCases->value)
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($actor)->exists()) {
            return;
        }

        throw new AuthorizationException('You cannot moderate this review.');
    }
}
