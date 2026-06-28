<?php

namespace App\Support\Payments;

final readonly class BookingSettlementBreakdown
{
    public function __construct(
        public int $grossAmount,
        public int $commissionBasisPoints,
        public int $commissionAmount,
        public int $providerFeeBasisPoints,
        public int $providerFeeFlatAmount,
        public int $providerFeeAmount,
        public int $netAmount,
    ) {}
}
