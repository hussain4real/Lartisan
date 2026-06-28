<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'customer_id' => User::factory(),
            'artisan_profile_id' => ArtisanProfile::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(),
            'status' => ReviewStatus::Published,
            'reviewed_at' => now(),
            'moderated_by' => null,
            'moderated_at' => null,
            'moderation_notes' => null,
            'artisan_response_by' => null,
            'artisan_response' => null,
            'artisan_responded_at' => null,
            'moderation_signal' => null,
            'moderation_score' => 0,
            'moderation_metadata' => null,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Hidden,
            'moderated_at' => now(),
            'moderation_notes' => 'Hidden by moderation.',
        ]);
    }

    public function pendingModeration(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::PendingModeration,
            'moderation_signal' => 'low_rating_keyword',
            'moderation_score' => 80,
            'moderation_notes' => 'Automatically routed for moderation.',
            'moderation_metadata' => ['keywords' => ['unsafe']],
        ]);
    }
}
