<?php

namespace Database\Factories;

use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportCase>
 */
class SupportCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'owner_id' => null,
            'supportable_type' => null,
            'supportable_id' => null,
            'category' => SupportCaseCategory::General,
            'priority' => SupportCasePriority::Normal,
            'status' => SupportCaseStatus::Open,
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'resolution_notes' => null,
            'opened_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolution_notes' => 'Resolved from factory.',
            'resolved_at' => now(),
            'status' => SupportCaseStatus::Resolved,
        ]);
    }
}
