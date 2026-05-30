<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\ArtisanProfile;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
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
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'payment_id' => null,
            'status' => SubscriptionStatus::Pending,
            'starts_at' => null,
            'ends_at' => null,
            'grace_ends_at' => null,
        ];
    }

    public function active(?Payment $payment = null): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_id' => $payment?->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'grace_ends_at' => now()->addDays(37),
        ]);
    }
}
