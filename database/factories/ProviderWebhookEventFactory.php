<?php

namespace Database\Factories;

use App\Enums\PaymentProviderName;
use App\Enums\ProviderWebhookEventStatus;
use App\Models\ProviderWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderWebhookEvent>
 */
class ProviderWebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = 'lartisan-'.fake()->unique()->lexify('????????');

        return [
            'provider' => PaymentProviderName::Paystack,
            'event' => 'charge.success',
            'provider_event_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'reference' => $reference,
            'payload' => ['event' => 'charge.success', 'data' => ['reference' => $reference]],
            'signature' => fake()->sha256(),
            'received_at' => now(),
            'processed_at' => null,
            'status' => ProviderWebhookEventStatus::Pending,
            'failure_reason' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => now(),
            'status' => ProviderWebhookEventStatus::Processed,
        ]);
    }
}
