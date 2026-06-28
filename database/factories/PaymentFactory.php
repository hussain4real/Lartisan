<?php

namespace Database\Factories;

use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\ArtisanProfile;
use App\Models\Booking;
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
            'booking_id' => null,
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'subscription_id' => null,
            'provider' => PaymentProviderName::Paystack,
            'purpose' => PaymentPurpose::Subscription,
            'status' => PaymentStatus::Pending,
            'reference' => 'lartisan-'.Str::lower((string) Str::ulid()),
            'provider_reference' => null,
            'amount' => fake()->numberBetween(500000, 5000000),
            'currency_code' => 'NGN',
            'commission_basis_points' => null,
            'commission_amount' => null,
            'provider_fee_basis_points' => null,
            'provider_fee_flat_amount' => null,
            'provider_fee_amount' => null,
            'net_amount' => null,
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

    public function booking(?Booking $booking = null): static
    {
        return $this->state(function (array $attributes) use ($booking): array {
            $booking ??= Booking::factory()->create();

            return [
                'artisan_profile_id' => $booking->artisan_profile_id,
                'booking_id' => $booking->id,
                'subscription_plan_id' => null,
                'purpose' => PaymentPurpose::Booking,
                'amount' => $booking->quoted_amount ?? 2500000,
                'currency_code' => $booking->currency_code,
                'commission_basis_points' => 1000,
                'commission_amount' => 250000,
                'provider_fee_basis_points' => 150,
                'provider_fee_flat_amount' => 10000,
                'provider_fee_amount' => 47500,
                'net_amount' => 2202500,
            ];
        });
    }
}
