<?php

namespace App\Actions\Payouts;

use App\Actions\Payments\PostWalletLedgerEntry;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\Payout;
use App\Models\WalletLedgerEntry;

class ReleaseFailedPayoutBalance
{
    public function __construct(
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Payout $payout,
        string $immutableReference,
        string $description,
        array $metadata = [],
    ): ?WalletLedgerEntry {
        $hasPayoutDebit = WalletLedgerEntry::query()
            ->where('source_type', $payout->getMorphClass())
            ->where('source_id', $payout->id)
            ->where('type', WalletLedgerEntryType::PayoutDebit)
            ->exists();

        if (! $hasPayoutDebit) {
            return null;
        }

        $existingRelease = WalletLedgerEntry::query()
            ->where('immutable_reference', $immutableReference)
            ->first();

        if ($existingRelease instanceof WalletLedgerEntry) {
            return $existingRelease;
        }

        return $this->postWalletLedgerEntry->handle(
            wallet: $payout->wallet()->firstOrFail(),
            type: WalletLedgerEntryType::AdjustmentCredit,
            direction: WalletLedgerDirection::Credit,
            amount: $payout->amount,
            source: $payout,
            immutableReference: $immutableReference,
            description: $description,
            metadata: $metadata,
        );
    }
}
