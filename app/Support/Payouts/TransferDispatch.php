<?php

namespace App\Support\Payouts;

class TransferDispatch
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $reference,
        public readonly ?string $transferCode,
        public readonly ?string $providerStatus,
        public readonly bool $actionRequired,
        public readonly ?string $failureReason,
        public readonly array $raw,
    ) {}
}
