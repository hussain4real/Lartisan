<?php

namespace Database\Factories;

use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\DisputeTargetType;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
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
            'payment_id' => null,
            'artisan_profile_id' => ArtisanProfile::factory(),
            'customer_id' => User::factory(),
            'opened_by_id' => User::factory(),
            'assigned_to_id' => null,
            'resolved_by_id' => null,
            'status' => DisputeStatus::Open,
            'severity' => DisputeSeverity::Medium,
            'target' => DisputeTargetType::Booking,
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'escalation_reason' => null,
            'resolution' => null,
            'opened_at' => now(),
            'escalated_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => ['source' => 'factory'],
            'money_adjustment_amount' => null,
            'money_adjustment_direction' => null,
            'money_adjustment_ledger_entry_id' => null,
            'money_adjusted_at' => null,
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

    public function paymentTarget(?Payment $payment = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_id' => $payment instanceof Payment ? $payment->id : Payment::factory(),
            'target' => DisputeTargetType::Payment,
        ]);
    }
}
