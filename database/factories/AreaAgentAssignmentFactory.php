<?php

namespace Database\Factories;

use App\Models\AreaAgentAssignment;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaAgentAssignment>
 */
class AreaAgentAssignmentFactory extends Factory
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
            'territory_id' => Territory::factory(),
            'starts_at' => now(),
            'ends_at' => null,
            'assigned_by' => null,
            'reason' => fake()->sentence(),
        ];
    }
}
