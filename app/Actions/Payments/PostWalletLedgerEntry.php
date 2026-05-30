<?php

namespace App\Actions\Payments;

use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PostWalletLedgerEntry
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Wallet $wallet,
        WalletLedgerEntryType $type,
        WalletLedgerDirection $direction,
        int $amount,
        ?Model $source = null,
        ?string $immutableReference = null,
        ?string $description = null,
        ?array $metadata = null,
    ): WalletLedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Ledger entry amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $wallet,
            $type,
            $direction,
            $amount,
            $source,
            $immutableReference,
            $description,
            $metadata,
        ): WalletLedgerEntry {
            $lockedWallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $availableBalance = $this->availableBalanceAfter($lockedWallet, $direction, $amount);
            $lockedWallet->forceFill(['available_balance' => $availableBalance])->save();

            return WalletLedgerEntry::query()->create([
                'wallet_id' => $lockedWallet->id,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'available_balance_after' => $lockedWallet->available_balance,
                'pending_balance_after' => $lockedWallet->pending_balance,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'immutable_reference' => $immutableReference ?? $this->reference(),
                'description' => $description,
                'metadata' => $metadata,
                'posted_at' => now(),
            ]);
        }, attempts: 3);
    }

    private function availableBalanceAfter(Wallet $wallet, WalletLedgerDirection $direction, int $amount): int
    {
        if ($direction === WalletLedgerDirection::Credit) {
            return $wallet->available_balance + $amount;
        }

        if ($wallet->available_balance < $amount) {
            throw new InvalidArgumentException('Wallet balance is insufficient for this ledger entry.');
        }

        return $wallet->available_balance - $amount;
    }

    private function reference(): string
    {
        do {
            $reference = 'wallet-'.Str::lower((string) Str::ulid());
        } while (WalletLedgerEntry::query()->where('immutable_reference', $reference)->exists());

        return $reference;
    }
}
