<?php

namespace Database\Factories;

use App\Enums\ReasonCodeCategory;
use App\Models\ReasonCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReasonCode>
 */
class ReasonCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $label */
        $label = fake()->unique()->words(3, true);

        return [
            'category' => ReasonCodeCategory::KycDecision,
            'code' => Str::slug($label),
            'label' => Str::title($label),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }

    public function kycDecision(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => ReasonCodeCategory::KycDecision,
        ]);
    }

    public function territoryAssignment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => ReasonCodeCategory::TerritoryAssignment,
        ]);
    }

    public function suspension(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => ReasonCodeCategory::Suspension,
        ]);
    }
}
