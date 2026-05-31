<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
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
            'payout_account_id' => PayoutAccount::factory(),
            'wallet_id' => Wallet::factory(),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'processed_by' => null,
            'status' => PayoutStatus::Pending,
            'amount' => 100000,
            'currency_code' => 'NGN',
            'requested_at' => now(),
            'approved_at' => null,
            'processing_at' => null,
            'paid_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
            'provider_reference' => null,
            'provider_transfer_code' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'approved_by' => $approver?->id,
            'approved_at' => now(),
            'status' => PayoutStatus::Approved,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paid_at' => now(),
            'provider_reference' => 'transfer-'.fake()->unique()->numerify('######'),
            'provider_transfer_code' => 'TRF_'.fake()->unique()->lexify('????????'),
            'status' => PayoutStatus::Paid,
        ]);
    }
}
