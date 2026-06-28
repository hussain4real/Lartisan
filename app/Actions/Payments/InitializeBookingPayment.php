<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Enums\BookingStatus;
use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class InitializeBookingPayment
{
    public function __construct(
        private readonly PaymentProvider $paymentProvider,
        private readonly CalculateBookingSettlement $calculateBookingSettlement,
    ) {}

    public function handle(Booking $booking, string $callbackUrl): Payment
    {
        $payment = DB::transaction(function () use ($booking): Payment {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::Accepted) {
                throw new InvalidArgumentException('Only accepted bookings can be paid.');
            }

            if ($lockedBooking->quoted_amount === null || $lockedBooking->quoted_amount <= 0) {
                throw new InvalidArgumentException('Booking has no payable amount.');
            }

            $existingPendingPayment = $lockedBooking->payments()
                ->where('purpose', PaymentPurpose::Booking->value)
                ->where('status', PaymentStatus::Pending->value)
                ->whereNotNull('checkout_url')
                ->latest('id')
                ->first();

            if ($existingPendingPayment instanceof Payment) {
                return $existingPendingPayment;
            }

            $breakdown = $this->calculateBookingSettlement->handle($lockedBooking->quoted_amount);
            $lockedBooking->forceFill(['payment_started_at' => now()])->save();

            return Payment::query()->create([
                'artisan_profile_id' => $lockedBooking->artisan_profile_id,
                'booking_id' => $lockedBooking->id,
                'provider' => PaymentProviderName::Paystack,
                'purpose' => PaymentPurpose::Booking,
                'status' => PaymentStatus::Pending,
                'reference' => $this->reference(),
                'amount' => $breakdown->grossAmount,
                'currency_code' => strtoupper($lockedBooking->currency_code),
                'commission_basis_points' => $breakdown->commissionBasisPoints,
                'commission_amount' => $breakdown->commissionAmount,
                'provider_fee_basis_points' => $breakdown->providerFeeBasisPoints,
                'provider_fee_flat_amount' => $breakdown->providerFeeFlatAmount,
                'provider_fee_amount' => $breakdown->providerFeeAmount,
                'net_amount' => $breakdown->netAmount,
            ]);
        }, attempts: 3);

        if ($payment->checkout_url !== null) {
            return $payment->refresh();
        }

        try {
            $initialization = $this->paymentProvider->initialize($payment, $callbackUrl);
        } catch (Throwable $throwable) {
            $payment->forceFill([
                'failed_at' => now(),
                'failure_reason' => $throwable->getMessage(),
                'status' => PaymentStatus::Failed,
            ])->save();

            throw $throwable;
        }

        $payment->forceFill([
            'access_code' => $initialization->accessCode,
            'checkout_url' => $initialization->authorizationUrl,
            'provider_payload' => $initialization->raw,
            'provider_reference' => $initialization->reference,
        ])->save();

        return $payment->refresh();
    }

    private function reference(): string
    {
        do {
            $reference = 'lartisan-booking-'.Str::lower((string) Str::ulid());
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
