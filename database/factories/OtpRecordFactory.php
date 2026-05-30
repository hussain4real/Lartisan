<?php

namespace Database\Factories;

use App\Enums\OtpPurpose;
use App\Models\OtpRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpRecord>
 */
class OtpRecordFactory extends Factory
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
            'phone_country_code' => '+234',
            'phone_number' => '8031234567',
            'phone_e164' => '+2348031234567',
            'email' => null,
            'purpose' => OtpPurpose::PhoneVerification,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'consumed_at' => null,
            'last_sent_at' => now(),
            'metadata' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verified_at' => now(),
            'consumed_at' => now(),
        ]);
    }
}
