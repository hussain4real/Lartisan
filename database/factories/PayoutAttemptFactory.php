<?php

namespace Database\Factories;

use App\Enums\PayoutAttemptStatus;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutAttempt>
 */
class PayoutAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payout_id' => Payout::factory(),
            'attempt_number' => 1,
            'status' => PayoutAttemptStatus::Processing,
            'provider_reference' => null,
            'failure_reason' => null,
            'provider_payload' => ['source' => 'factory'],
            'processed_at' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PayoutAttemptStatus::Successful,
            'provider_reference' => 'transfer-'.fake()->unique()->numerify('######'),
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'failure_reason' => 'Provider failed.',
            'status' => PayoutAttemptStatus::Failed,
            'processed_at' => now(),
        ]);
    }
}
