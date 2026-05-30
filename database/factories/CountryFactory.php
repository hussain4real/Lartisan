<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'name' => $name,
            'iso_code' => Str::upper(fake()->unique()->lexify('??')),
            'currency_code' => Str::upper(fake()->lexify('???')),
            'phone_country_code' => '+'.fake()->numberBetween(1, 999),
            'active' => true,
        ];
    }
}
