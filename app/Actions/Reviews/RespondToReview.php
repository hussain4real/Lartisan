<?php

namespace App\Actions\Reviews;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ReviewStatus;
use App\Models\ArtisanProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RespondToReview
{
    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}

    public function handle(Review $review, User $actor, string $response): Review
    {
        $response = trim($response);

        if ($response === '') {
            throw new InvalidArgumentException('A review response is required.');
        }

        if (mb_strlen($response) > 1200) {
            throw new InvalidArgumentException('A review response may not be greater than 1200 characters.');
        }

        return DB::transaction(function () use ($review, $actor, $response): Review {
            $lockedReview = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $profile = $lockedReview->artisanProfile()->firstOrFail();

            $this->authorize($actor, $profile, $lockedReview);

            $before = [
                'artisan_response' => $lockedReview->artisan_response,
                'artisan_response_by' => $lockedReview->artisan_response_by,
            ];

            $lockedReview->forceFill([
                'artisan_response_by' => $actor->id,
                'artisan_response' => $response,
                'artisan_responded_at' => now(),
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'review.responded',
                subject: $lockedReview,
                before: $before,
                after: [
                    'artisan_response_by' => $actor->id,
                    'artisan_response' => $response,
                ],
                reason: $response,
            );

            return $lockedReview->refresh();
        }, attempts: 3);
    }

    private function authorize(User $actor, ArtisanProfile $profile, Review $review): void
    {
        if ($profile->user_id !== $actor->id) {
            throw new AuthorizationException('Only the reviewed artisan can respond to this review.');
        }

        if ($review->status === ReviewStatus::Hidden) {
            throw new AuthorizationException('Hidden reviews cannot receive public artisan responses.');
        }
    }
}
