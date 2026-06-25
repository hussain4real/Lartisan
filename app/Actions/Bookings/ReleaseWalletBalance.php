<?php

namespace App\Actions\Bookings;

use App\Actions\Notifications\SendLifecycleNotification;
use App\Actions\Payments\EnsureWallet;
use App\Actions\Payments\PostWalletLedgerEntry;
use App\Enums\BookingStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletLedgerBalance;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReleaseWalletBalance
{
    public function __construct(
        private readonly EnsureWallet $ensureWallet,
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Booking $booking): WalletLedgerEntry
    {
        $settlementCredit = DB::transaction(function () use ($booking): WalletLedgerEntry {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $existingEntry = WalletLedgerEntry::query()
                ->where('source_type', $lockedBooking->getMorphClass())
                ->where('source_id', $lockedBooking->id)
                ->where('type', WalletLedgerEntryType::SettlementCredit)
                ->first();

            if ($existingEntry instanceof WalletLedgerEntry) {
                if (! in_array($lockedBooking->status, [BookingStatus::Confirmed, BookingStatus::Settled], true)) {
                    throw new InvalidArgumentException('Only confirmed bookings can release wallet balance.');
                }

                $lockedBooking->forceFill([
                    'settled_at' => $lockedBooking->settled_at ?? now(),
                    'status' => BookingStatus::Settled,
                    'wallet_released_at' => $lockedBooking->wallet_released_at ?? now(),
                ])->save();

                return $existingEntry;
            }

            if ($lockedBooking->status !== BookingStatus::Confirmed) {
                throw new InvalidArgumentException('Only confirmed bookings can release wallet balance.');
            }

            $payment = $this->successfulBookingPayment($lockedBooking);
            $netAmount = $payment->net_amount;

            if ($netAmount === null || $netAmount <= 0) {
                throw new InvalidArgumentException('Booking has no releasable amount.');
            }

            $wallet = $this->ensureWallet->handle(
                profile: $lockedBooking->artisanProfile()->firstOrFail(),
                currencyCode: $lockedBooking->currency_code,
            );
            $this->postWalletLedgerEntry->handle(
                wallet: $wallet,
                type: WalletLedgerEntryType::SettlementDebit,
                direction: WalletLedgerDirection::Debit,
                amount: $netAmount,
                source: $lockedBooking,
                immutableReference: 'booking-'.$lockedBooking->id.'-settlement-pending-release',
                description: 'Booking escrow released from pending balance',
                metadata: ['payment_reference' => $payment->reference],
                balance: WalletLedgerBalance::Pending,
            );
            $settlementCredit = $this->postWalletLedgerEntry->handle(
                wallet: $wallet,
                type: WalletLedgerEntryType::SettlementCredit,
                direction: WalletLedgerDirection::Credit,
                amount: $netAmount,
                source: $lockedBooking,
                immutableReference: 'booking-'.$lockedBooking->id.'-settlement-credit',
                description: 'Booking net settlement released to artisan wallet',
                metadata: [
                    'gross_amount' => $payment->amount,
                    'commission_amount' => $payment->commission_amount,
                    'payment_reference' => $payment->reference,
                    'provider_fee_amount' => $payment->provider_fee_amount,
                    'tracker_code' => $lockedBooking->tracker_code,
                ],
            );
            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'settled_at' => now(),
                'status' => BookingStatus::Settled,
                'wallet_released_at' => now(),
            ])->save();
            $this->recordBookingStatus->handle(
                $lockedBooking,
                null,
                $fromStatus,
                BookingStatus::Settled,
                'booking.settled',
                ['payment_reference' => $payment->reference, 'net_amount' => $netAmount],
            );

            return $settlementCredit;
        }, attempts: 3);

        $booking->refresh();
        $this->sendLifecycleNotification->bookingStatusChanged($booking, BookingStatus::Settled);

        return $settlementCredit;
    }

    private function successfulBookingPayment(Booking $booking): Payment
    {
        $payment = $booking->payments()
            ->where('purpose', PaymentPurpose::Booking->value)
            ->where('status', PaymentStatus::Successful->value)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (! $payment instanceof Payment) {
            throw new InvalidArgumentException('Booking has no successful payment to settle.');
        }

        return $payment;
    }
}
