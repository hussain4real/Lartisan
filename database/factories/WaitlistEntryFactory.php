<?php

namespace Database\Factories;

use App\Enums\WaitlistAudienceType;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::factory();
        $state = State::factory()->for($country);
        $localGovernment = LocalGovernment::factory()->for($state);
        $territory = Territory::factory()->for($localGovernment);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'audience_type' => WaitlistAudienceType::Artisan->value,
            'business_name' => fake()->company(),
            'service_category_id' => ServiceCategory::factory(),
            'country_id' => $country,
            'state_id' => $state,
            'local_government_id' => $localGovernment,
            'territory_id' => $territory,
            'note' => fake()->sentence(),
            'contact_consent' => true,
        ];
    }
}
