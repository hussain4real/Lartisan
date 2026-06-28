<?php

namespace App\Actions\Payments;

use App\Actions\Bookings\RecordBookingStatus;
use App\Actions\Notifications\SendLifecycleNotification;
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

class EscrowBookingPayment
{
    public function __construct(
        private readonly EnsureWallet $ensureWallet,
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Payment $payment): Booking
    {
        $booking = DB::transaction(function () use ($payment): Booking {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->purpose !== PaymentPurpose::Booking) {
                throw new InvalidArgumentException('Only booking payments can be escrowed.');
            }

            if ($lockedPayment->status !== PaymentStatus::Successful) {
                throw new InvalidArgumentException('Only successful booking payments can be escrowed.');
            }

            $booking = $lockedPayment->booking()->lockForUpdate()->firstOrFail();

            if (in_array($booking->status, [
                BookingStatus::Escrowed,
                BookingStatus::InProgress,
                BookingStatus::Finished,
                BookingStatus::Confirmed,
                BookingStatus::Settled,
                BookingStatus::Reviewed,
            ], true)) {
                if (! $this->hasEscrowCredit($booking)) {
                    throw new InvalidArgumentException('Booking escrow ledger is missing.');
                }

                return $booking->refresh();
            }

            if (! in_array($booking->status, [BookingStatus::Accepted, BookingStatus::Paid], true)) {
                throw new InvalidArgumentException('Only accepted bookings can be escrowed.');
            }

            $this->recordStatus($booking, BookingStatus::Paid, 'booking.paid', [
                'payment_reference' => $lockedPayment->reference,
            ]);
            $this->postEscrowLedgerEntries($booking, $lockedPayment);
            $this->recordStatus($booking, BookingStatus::Escrowed, 'booking.escrowed', [
                'payment_reference' => $lockedPayment->reference,
                'net_amount' => $lockedPayment->net_amount,
            ]);

            return $booking->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->bookingStatusChanged($booking, BookingStatus::Paid);
        $this->sendLifecycleNotification->bookingStatusChanged($booking, BookingStatus::Escrowed);

        return $booking->refresh();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordStatus(Booking $booking, BookingStatus $toStatus, string $notes, array $metadata = []): void
    {
        if ($booking->status === $toStatus) {
            return;
        }

        $fromStatus = $booking->status;
        $booking->forceFill([
            'status' => $toStatus,
            'paid_at' => $toStatus === BookingStatus::Paid ? ($booking->paid_at ?? now()) : $booking->paid_at,
            'escrowed_at' => $toStatus === BookingStatus::Escrowed ? ($booking->escrowed_at ?? now()) : $booking->escrowed_at,
        ])->save();

        $this->recordBookingStatus->handle($booking, null, $fromStatus, $toStatus, $notes, $metadata);
    }

    private function postEscrowLedgerEntries(Booking $booking, Payment $payment): void
    {
        $wallet = $this->ensureWallet->handle(
            profile: $booking->artisanProfile()->firstOrFail(),
            currencyCode: $booking->currency_code,
        );

        $this->postIfMissing(
            booking: $booking,
            type: WalletLedgerEntryType::BookingCredit,
            direction: WalletLedgerDirection::Credit,
            amount: $payment->amount,
            immutableReference: "booking-{$booking->id}-gross-escrow",
            description: 'Booking payment held in escrow',
            metadata: ['payment_reference' => $payment->reference],
        );

        if (($payment->commission_amount ?? 0) > 0) {
            $this->postIfMissing(
                booking: $booking,
                type: WalletLedgerEntryType::CommissionDebit,
                direction: WalletLedgerDirection::Debit,
                amount: $payment->commission_amount,
                immutableReference: "booking-{$booking->id}-commission",
                description: 'Platform commission deducted from escrow',
                metadata: [
                    'basis_points' => $payment->commission_basis_points,
                    'payment_reference' => $payment->reference,
                ],
            );
        }

        if (($payment->provider_fee_amount ?? 0) > 0) {
            $this->postIfMissing(
                booking: $booking,
                type: WalletLedgerEntryType::FeeDebit,
                direction: WalletLedgerDirection::Debit,
                amount: $payment->provider_fee_amount,
                immutableReference: "booking-{$booking->id}-provider-fee",
                description: 'Payment provider fee deducted from escrow',
                metadata: [
                    'basis_points' => $payment->provider_fee_basis_points,
                    'flat_amount' => $payment->provider_fee_flat_amount,
                    'payment_reference' => $payment->reference,
                ],
            );
        }

        $wallet->refresh();
    }

    private function hasEscrowCredit(Booking $booking): bool
    {
        return WalletLedgerEntry::query()
            ->where('source_type', $booking->getMorphClass())
            ->where('source_id', $booking->id)
            ->where('type', WalletLedgerEntryType::BookingCredit)
            ->where('immutable_reference', "booking-{$booking->id}-gross-escrow")
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function postIfMissing(
        Booking $booking,
        WalletLedgerEntryType $type,
        WalletLedgerDirection $direction,
        int $amount,
        string $immutableReference,
        string $description,
        array $metadata,
    ): void {
        if (WalletLedgerEntry::query()->where('immutable_reference', $immutableReference)->exists()) {
            return;
        }

        $wallet = $this->ensureWallet->handle(
            profile: $booking->artisanProfile()->firstOrFail(),
            currencyCode: $booking->currency_code,
        );

        $this->postWalletLedgerEntry->handle(
            wallet: $wallet,
            type: $type,
            direction: $direction,
            amount: $amount,
            source: $booking,
            immutableReference: $immutableReference,
            description: $description,
            metadata: $metadata,
            balance: WalletLedgerBalance::Pending,
        );
    }
}
