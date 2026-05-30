<?php

namespace Database\Factories;

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Models\ArtisanProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtisanProfile>
 */
class ArtisanProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'verification_status' => ArtisanVerificationStatus::Draft,
            'subscription_status' => ArtisanSubscriptionStatus::Trial,
            'availability_status' => ArtisanAvailabilityStatus::Offline,
            'country_id' => null,
            'state_id' => null,
            'local_government_id' => null,
            'territory_id' => null,
            'onboarded_by_agent_id' => null,
            'approved_by' => null,
            'approved_at' => null,
            'is_public' => false,
            'internal_notes' => null,
        ];
    }
}
