<?php

namespace Database\Factories;

use App\Enums\ArtisanServiceStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtisanService>
 */
class ArtisanServiceFactory extends Factory
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
            'service_category_id' => ServiceCategory::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'starting_price' => fake()->randomFloat(2, 5000, 75000),
            'currency_code' => 'NGN',
            'status' => ArtisanServiceStatus::Draft,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArtisanServiceStatus::Active,
        ]);
    }
}
