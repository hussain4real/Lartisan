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
            'team_id' => Team::factory()->artisanBusiness(),
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'public_summary' => fake()->sentence(12),
            'years_experience' => fake()->numberBetween(1, 25),
            'service_radius_km' => fake()->numberBetween(5, 40),
            'public_phone' => '+234'.fake()->numerify('80########'),
            'public_email' => fake()->safeEmail(),
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
            'suspension_reason_code_id' => null,
            'suspended_by' => null,
            'suspended_at' => null,
        ];
    }
}
