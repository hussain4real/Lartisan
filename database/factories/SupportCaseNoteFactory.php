<?php

namespace Database\Factories;

use App\Models\SupportCase;
use App\Models\SupportCaseNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportCaseNote>
 */
class SupportCaseNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_case_id' => SupportCase::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => true,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
