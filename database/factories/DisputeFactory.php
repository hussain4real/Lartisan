<?php

namespace Database\Factories;

use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
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
            'review_id' => null,
            'artisan_profile_id' => ArtisanProfile::factory(),
            'customer_id' => User::factory(),
            'opened_by_id' => User::factory(),
            'assigned_to_id' => null,
            'resolved_by_id' => null,
            'status' => DisputeStatus::Open,
            'severity' => DisputeSeverity::Medium,
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'escalation_reason' => null,
            'resolution' => null,
            'opened_at' => now(),
            'escalated_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function escalated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DisputeStatus::EscalatedToState,
            'severity' => DisputeSeverity::High,
            'escalation_reason' => 'Escalated from factory.',
            'escalated_at' => now(),
        ]);
    }

    public function resolved(?User $resolver = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DisputeStatus::Resolved,
            'resolved_by_id' => $resolver?->id,
            'resolution' => 'Resolved from factory.',
            'resolved_at' => now(),
        ]);
    }
}
