<?php

namespace Database\Factories;

use App\Enums\TeamKind;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'kind' => TeamKind::Workspace,
            'is_personal' => false,
        ];
    }

    /**
     * Indicate that the team is a personal team.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => TeamKind::Personal,
            'is_personal' => true,
        ]);
    }

    /**
     * Indicate that the team is an artisan business workspace.
     */
    public function artisanBusiness(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => TeamKind::ArtisanBusiness,
            'is_personal' => false,
        ]);
    }

    /**
     * Indicate that the team has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
