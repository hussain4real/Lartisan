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
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundBookingPayment
{
    public function __construct(
        private readonly EnsureWallet $ensureWallet,
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
        private readonly RecordBookingStatus $recordBookingStatus,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Booking $booking, ?string $reason = null): Booking
    {
        $updatedBooking = DB::transaction(function () use ($booking, $reason): Booking {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status === BookingStatus::Refunded) {
                return $lockedBooking->refresh();
            }

            if (in_array($lockedBooking->status, [BookingStatus::Settled, BookingStatus::Reviewed], true)) {
                throw new InvalidArgumentException('Settled bookings require an adjustment instead of a direct refund.');
            }

            $payment = $this->successfulBookingPayment($lockedBooking);
            $wallet = $this->ensureWallet->handle(
                profile: $lockedBooking->artisanProfile()->firstOrFail(),
                currencyCode: $lockedBooking->currency_code,
            );
            $deductedAmount = (int) ($payment->commission_amount ?? 0) + (int) ($payment->provider_fee_amount ?? 0);

            if ($deductedAmount > 0) {
                $this->postIfMissing(
                    wallet: $wallet,
                    booking: $lockedBooking,
                    type: WalletLedgerEntryType::AdjustmentCredit,
                    direction: WalletLedgerDirection::Credit,
                    amount: $deductedAmount,
                    immutableReference: "booking-{$lockedBooking->id}-refund-adjustment",
                    description: 'Booking refund adjustment for commission and provider fees',
                    metadata: ['payment_reference' => $payment->reference, 'reason' => $reason],
                );
            }

            $this->postIfMissing(
                wallet: $wallet,
                booking: $lockedBooking,
                type: WalletLedgerEntryType::RefundDebit,
                direction: WalletLedgerDirection::Debit,
                amount: $payment->amount,
                immutableReference: "booking-{$lockedBooking->id}-refund",
                description: 'Booking payment refunded from escrow',
                metadata: ['payment_reference' => $payment->reference, 'reason' => $reason],
            );

            $payment->forceFill(['status' => PaymentStatus::Refunded])->save();

            $fromStatus = $lockedBooking->status;
            $lockedBooking->forceFill([
                'refunded_at' => now(),
                'status' => BookingStatus::Refunded,
            ])->save();
            $this->recordBookingStatus->handle(
                $lockedBooking,
                null,
                $fromStatus,
                BookingStatus::Refunded,
                'booking.refunded',
                ['payment_reference' => $payment->reference, 'reason' => $reason],
            );

            return $lockedBooking->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->bookingStatusChanged($updatedBooking, BookingStatus::Refunded);

        return $updatedBooking->refresh();
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
            throw new InvalidArgumentException('Booking has no successful payment to refund.');
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function postIfMissing(
        Wallet $wallet,
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
