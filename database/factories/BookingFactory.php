<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(40);

        return [
            'customer_id' => null,
            'artisan_profile_id' => ArtisanProfile::factory(),
            'artisan_service_id' => null,
            'service_category_id' => null,
            'status' => BookingStatus::Requested,
            'customer_name' => fake()->name(),
            'customer_phone' => '+234'.fake()->numerify('80########'),
            'customer_email' => fake()->safeEmail(),
            'scheduled_at' => now()->addDay(),
            'description' => fake()->sentence(),
            'quoted_amount' => 2500000,
            'currency_code' => 'NGN',
            'address_snapshot' => [
                'line_1' => fake()->streetAddress(),
                'line_2' => null,
                'landmark' => fake()->streetName(),
                'state_id' => null,
                'local_government_id' => null,
                'territory_id' => null,
            ],
            'country_id' => null,
            'state_id' => null,
            'local_government_id' => null,
            'territory_id' => null,
            'tracker_code' => 'BK-'.Str::upper(Str::random(10)),
            'secure_token_hash' => hash('sha256', $token),
            'accepted_at' => null,
            'rejected_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'confirmed_at' => null,
            'wallet_released_at' => null,
        ];
    }

    public function forCustomer(User $customer): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone_e164 ?? '+2348030000000',
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::InProgress,
            'accepted_at' => now()->subHour(),
            'started_at' => now(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Finished,
            'accepted_at' => now()->subHours(2),
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
    }
}
