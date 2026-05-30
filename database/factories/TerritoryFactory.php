<?php

namespace Database\Factories;

use App\Enums\TerritoryType;
use App\Models\LocalGovernment;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'local_government_id' => LocalGovernment::factory(),
            'type' => TerritoryType::Ward,
            'name' => $name,
            'slug' => Str::slug($name),
            'boundaries' => null,
            'active' => true,
        ];
    }
}
