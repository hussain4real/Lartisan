<?php

namespace Database\Factories;

use App\Enums\ArtisanVerificationStatus;
use App\Enums\KycRiskLevel;
use App\Models\ArtisanProfile;
use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycSubmission>
 */
class KycSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artisan_profile_id' => ArtisanProfile::factory(),
            'status' => ArtisanVerificationStatus::Draft,
            'risk_level' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_reason' => null,
            'reason_code_id' => null,
            'notes' => fake()->sentence(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArtisanVerificationStatus::Submitted,
            'risk_level' => KycRiskLevel::Low,
            'submitted_at' => now(),
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'decision_reason' => 'Reviewed by operations.',
        ]);
    }
}
