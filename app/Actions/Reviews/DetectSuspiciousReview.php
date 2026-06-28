<?php

namespace App\Actions\Reviews;

use App\Models\Booking;
use Illuminate\Support\Str;

class DetectSuspiciousReview
{
    /**
     * @return array{is_suspicious: bool, signal: string|null, score: int, reason: string|null, metadata: array<string, mixed>}
     */
    public function handle(Booking $booking, int $rating, ?string $comment): array
    {
        $score = 0;
        $signals = [];
        $keywords = $this->matchingKeywords($comment);
        $lowRatingThreshold = $this->integerConfig('lartisan.trust.low_rating_threshold', 2);

        if ($rating <= $lowRatingThreshold) {
            $score += 35;
            $signals[] = 'low_rating';
        }

        if ($keywords !== []) {
            $score += 45;
            $signals[] = 'keyword';
        }

        if ($rating <= $lowRatingThreshold && trim((string) $comment) === '') {
            $score += 15;
            $signals[] = 'low_rating_no_comment';
        }

        $artisanProfile = $booking->artisanProfile()->first();
        $recentReviewsForProfile = $artisanProfile?->reviews()
            ->where('created_at', '>=', now()->subDay())
            ->count() ?? 0;

        if ($recentReviewsForProfile >= 5) {
            $score += 20;
            $signals[] = 'review_velocity';
        }

        $threshold = $this->integerConfig('lartisan.trust.suspicious_review_score_threshold', 50);
        $isSuspicious = $score >= $threshold;

        return [
            'is_suspicious' => $isSuspicious,
            'signal' => $signals === [] ? null : implode('_', $signals),
            'score' => min(100, $score),
            'reason' => $isSuspicious ? 'Automatically routed for moderation.' : null,
            'metadata' => [
                'booking_id' => $booking->id,
                'artisan_profile_id' => $booking->artisan_profile_id,
                'keywords' => $keywords,
                'signals' => $signals,
                'threshold' => $threshold,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function matchingKeywords(?string $comment): array
    {
        $normalizedComment = Str::lower((string) $comment);

        if ($normalizedComment === '') {
            return [];
        }

        $keywords = config('lartisan.trust.suspicious_review_keywords', []);

        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_filter($keywords, fn (mixed $keyword): bool => is_string($keyword)
            && $keyword !== ''
            && Str::contains($normalizedComment, Str::lower($keyword))));
    }

    private function integerConfig(string $key, int $fallback): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $fallback;
    }
}
