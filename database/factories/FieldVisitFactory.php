<?php

namespace Database\Factories;

use App\Enums\FieldVisitStatus;
use App\Models\ArtisanProfile;
use App\Models\FieldVisit;
use App\Models\KycSubmission;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldVisit>
 */
class FieldVisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kyc_submission_id' => KycSubmission::factory(),
            'artisan_profile_id' => ArtisanProfile::factory(),
            'area_agent_id' => User::factory(),
            'territory_id' => Territory::factory(),
            'status' => FieldVisitStatus::Scheduled,
            'visited_at' => null,
            'latitude' => null,
            'longitude' => null,
            'notes' => fake()->sentence(),
            'checklist' => [
                'shop_exists' => true,
                'identity_seen' => false,
            ],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FieldVisitStatus::Completed,
            'visited_at' => now(),
            'latitude' => '9.0764780',
            'longitude' => '7.4686590',
        ]);
    }
}
