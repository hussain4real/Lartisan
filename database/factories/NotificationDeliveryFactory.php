<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
            'channel' => NotificationChannel::Email,
            'event_type' => NotificationEventType::ProviderCallback,
            'recipient_address' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'status' => NotificationDeliveryStatus::Pending,
            'subject' => fake()->sentence(3),
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => NotificationChannel::Whatsapp,
            'provider' => 'whatsapp',
            'provider_message_id' => fake()->uuid(),
            'recipient_address' => '+2348030000000',
        ]);
    }
}
