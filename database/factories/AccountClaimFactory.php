<?php

namespace Database\Factories;

use App\Enums\AccountClaimStatus;
use App\Models\AccountClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountClaim>
 */
class AccountClaimFactory extends Factory
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
            'claimed_by' => null,
            'token_hash' => hash('sha256', fake()->unique()->sha256()),
            'status' => AccountClaimStatus::Pending,
            'expires_at' => now()->addDay(),
            'claimed_at' => null,
            'metadata' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountClaimStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function claimed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'claimed_by' => $attributes['user_id'] ?? User::factory(),
            'status' => AccountClaimStatus::Claimed,
            'claimed_at' => now(),
        ]);
    }
}
