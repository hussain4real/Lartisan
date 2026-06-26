<?php

namespace App\Support\Payouts;

class BankAccountResolution
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $accountName,
        public readonly ?string $accountNumber,
        public readonly ?string $bankCode,
        public readonly ?string $bankName,
        public readonly ?string $providerStatus,
        public readonly ?string $failureReason,
        public readonly array $raw,
    ) {}
}
