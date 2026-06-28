<?php

namespace Database\Factories;

use App\Enums\BookingMessageSenderRole;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingMessage>
 */
class BookingMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'sender_id' => User::factory(),
            'sender_role' => BookingMessageSenderRole::Customer,
            'body' => fake()->sentence(),
            'metadata' => ['source' => 'factory'],
        ];
    }
}
