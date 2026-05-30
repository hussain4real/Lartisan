<?php

namespace Database\Factories;

use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletLedgerEntry>
 */
class WalletLedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => WalletLedgerEntryType::BookingCredit,
            'direction' => WalletLedgerDirection::Credit,
            'amount' => 100000,
            'available_balance_after' => 100000,
            'pending_balance_after' => 0,
            'source_type' => null,
            'source_id' => null,
            'immutable_reference' => 'ledger-'.Str::lower((string) Str::ulid()),
            'description' => fake()->sentence(),
            'metadata' => ['source' => 'factory'],
            'posted_at' => now(),
        ];
    }
}
