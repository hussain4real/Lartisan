<?php

namespace App\Actions\Payments;

use App\Enums\WalletLedgerBalance;
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
        WalletLedgerBalance $balance = WalletLedgerBalance::Available,
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
            $balance,
        ): WalletLedgerEntry {
            $lockedWallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            [$availableBalance, $pendingBalance] = $this->balancesAfter($lockedWallet, $direction, $amount, $balance);
            $lockedWallet->forceFill([
                'available_balance' => $availableBalance,
                'pending_balance' => $pendingBalance,
            ])->save();

            return WalletLedgerEntry::query()->create([
                'wallet_id' => $lockedWallet->id,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'available_balance_after' => $availableBalance,
                'pending_balance_after' => $pendingBalance,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'immutable_reference' => $immutableReference ?? $this->reference(),
                'description' => $description,
                'metadata' => $metadata,
                'posted_at' => now(),
            ]);
        }, attempts: 3);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function balancesAfter(
        Wallet $wallet,
        WalletLedgerDirection $direction,
        int $amount,
        WalletLedgerBalance $balance,
    ): array {
        if ($balance === WalletLedgerBalance::Pending) {
            return [
                $wallet->available_balance,
                $this->balanceAfter($wallet->pending_balance, $direction, $amount),
            ];
        }

        return [
            $this->balanceAfter($wallet->available_balance, $direction, $amount),
            $wallet->pending_balance,
        ];
    }

    private function balanceAfter(int $currentBalance, WalletLedgerDirection $direction, int $amount): int
    {
        if ($direction === WalletLedgerDirection::Credit) {
            return $currentBalance + $amount;
        }

        if ($currentBalance < $amount) {
            throw new InvalidArgumentException('Wallet balance is insufficient for this ledger entry.');
        }

        return $currentBalance - $amount;
    }

    private function reference(): string
    {
        do {
            $reference = 'wallet-'.Str::lower((string) Str::ulid());
        } while (WalletLedgerEntry::query()->where('immutable_reference', $reference)->exists());

        return $reference;
    }
}
