<?php

namespace Database\Factories;

use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\ArtisanProfile;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'subscription_id' => null,
            'provider' => PaymentProviderName::Paystack,
            'purpose' => PaymentPurpose::Subscription,
            'status' => PaymentStatus::Pending,
            'reference' => 'lartisan-'.Str::lower((string) Str::ulid()),
            'provider_reference' => null,
            'amount' => fake()->numberBetween(500000, 5000000),
            'currency_code' => 'NGN',
            'checkout_url' => null,
            'access_code' => null,
            'provider_payload' => null,
            'paid_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_reference' => $attributes['reference'] ?? 'paystack-reference',
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => 'Payment failed.',
        ]);
    }
}
