<?php

namespace Database\Factories;

use App\Enums\PayoutBatchStatus;
use App\Models\PayoutBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutBatch>
 */
class PayoutBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => PayoutBatchStatus::Pending,
            'scheduled_for' => now(),
            'started_at' => null,
            'completed_at' => null,
            'total_payouts' => 0,
            'successful_payouts' => 0,
            'failed_payouts' => 0,
            'action_required_payouts' => 0,
            'total_amount' => 0,
            'currency_code' => 'NGN',
            'created_by' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => now(),
            'status' => PayoutBatchStatus::Processing,
        ]);
    }
}
