<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'default_address_id' => null,
            'preferences' => [
                'preferred_channel' => fake()->randomElement(['email', 'sms', 'whatsapp']),
            ],
        ];
    }
}
