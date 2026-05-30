<?php

namespace Database\Factories;

use App\Enums\ArtisanVerificationStatus;
use App\Models\ArtisanProfile;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusHistory>
 */
class StatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statusable_type' => ArtisanProfile::class,
            'statusable_id' => ArtisanProfile::factory(),
            'actor_id' => User::factory(),
            'from_status' => ArtisanVerificationStatus::Draft->value,
            'to_status' => ArtisanVerificationStatus::Submitted->value,
            'reason' => fake()->sentence(),
            'metadata' => null,
        ];
    }
}
