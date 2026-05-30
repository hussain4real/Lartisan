<?php

namespace App\Actions\Bookings;

use App\Actions\Payments\EnsureWallet;
use App\Actions\Payments\PostWalletLedgerEntry;
use App\Enums\BookingStatus;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\Booking;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReleaseWalletBalance
{
    public function __construct(
        private readonly EnsureWallet $ensureWallet,
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
    ) {}

    public function handle(Booking $booking): WalletLedgerEntry
    {
        return DB::transaction(function () use ($booking): WalletLedgerEntry {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::Confirmed) {
                throw new InvalidArgumentException('Only confirmed bookings can release wallet balance.');
            }

            if ($lockedBooking->quoted_amount === null || $lockedBooking->quoted_amount <= 0) {
                throw new InvalidArgumentException('Booking has no releasable amount.');
            }

            $existingEntry = WalletLedgerEntry::query()
                ->where('source_type', $lockedBooking->getMorphClass())
                ->where('source_id', $lockedBooking->id)
                ->where('type', WalletLedgerEntryType::BookingCredit)
                ->first();

            if ($existingEntry instanceof WalletLedgerEntry) {
                $lockedBooking->forceFill(['wallet_released_at' => $lockedBooking->wallet_released_at ?? now()])->save();

                return $existingEntry;
            }

            $wallet = $this->ensureWallet->handle(
                profile: $lockedBooking->artisanProfile()->firstOrFail(),
                currencyCode: $lockedBooking->currency_code,
            );
            $ledgerEntry = $this->postWalletLedgerEntry->handle(
                wallet: $wallet,
                type: WalletLedgerEntryType::BookingCredit,
                direction: WalletLedgerDirection::Credit,
                amount: $lockedBooking->quoted_amount,
                source: $lockedBooking,
                immutableReference: 'booking-'.$lockedBooking->id.'-release',
                description: 'Booking completion release',
                metadata: ['tracker_code' => $lockedBooking->tracker_code],
            );
            $lockedBooking->forceFill(['wallet_released_at' => now()])->save();

            return $ledgerEntry;
        }, attempts: 3);
    }
}
