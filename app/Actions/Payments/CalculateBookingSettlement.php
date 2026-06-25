<?php

namespace App\Actions\Payments;

use App\Support\Payments\BookingSettlementBreakdown;
use InvalidArgumentException;

class CalculateBookingSettlement
{
    public function handle(int $grossAmount): BookingSettlementBreakdown
    {
        if ($grossAmount <= 0) {
            throw new InvalidArgumentException('Booking payment amount must be greater than zero.');
        }

        $commissionBasisPoints = $this->configInteger('commission_basis_points');
        $providerFeeBasisPoints = $this->configInteger('provider_fee_basis_points');
        $providerFeeFlatAmount = $this->configInteger('provider_fee_flat_amount');

        $commissionAmount = intdiv($grossAmount * $commissionBasisPoints, 10_000);
        $providerFeeAmount = intdiv($grossAmount * $providerFeeBasisPoints, 10_000) + $providerFeeFlatAmount;
        $netAmount = $grossAmount - $commissionAmount - $providerFeeAmount;

        if ($netAmount <= 0) {
            throw new InvalidArgumentException('Booking payment fees exceed the gross payment amount.');
        }

        return new BookingSettlementBreakdown(
            grossAmount: $grossAmount,
            commissionBasisPoints: $commissionBasisPoints,
            commissionAmount: $commissionAmount,
            providerFeeBasisPoints: $providerFeeBasisPoints,
            providerFeeFlatAmount: $providerFeeFlatAmount,
            providerFeeAmount: $providerFeeAmount,
            netAmount: $netAmount,
        );
    }

    private function configInteger(string $key): int
    {
        $value = config("lartisan.booking_payments.{$key}");

        return max(0, is_numeric($value) ? (int) $value : 0);
    }
}
