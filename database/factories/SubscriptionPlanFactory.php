<?php

namespace Database\Factories;

use App\Enums\SubscriptionInterval;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->unique()->word();

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'price_amount' => fake()->numberBetween(500000, 5000000),
            'currency_code' => 'NGN',
            'interval' => SubscriptionInterval::Monthly,
            'duration_days' => 30,
            'sort_order' => fake()->numberBetween(1, 50),
            'active' => true,
            'feature_summary' => ['Public listing', 'Lead access'],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
