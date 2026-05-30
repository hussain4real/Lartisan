<?php

namespace Database\Factories;

use App\Models\LocalGovernment;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LocalGovernment>
 */
class LocalGovernmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'state_id' => State::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'active' => true,
        ];
    }
}
