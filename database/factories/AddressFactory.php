<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Office', 'Shop']),
            'contact_name' => fake()->name(),
            'phone' => '+234'.fake()->numerify('80########'),
            'country_id' => Country::factory(),
            'state_id' => State::factory(),
            'local_government_id' => LocalGovernment::factory(),
            'territory_id' => Territory::factory(),
            'line_1' => fake()->streetAddress(),
            'line_2' => null,
            'landmark' => fake()->optional()->streetName(),
            'latitude' => fake()->latitude(4, 14),
            'longitude' => fake()->longitude(3, 15),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
