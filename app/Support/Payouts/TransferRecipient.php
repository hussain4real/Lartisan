<?php

namespace App\Support\Payouts;

class TransferRecipient
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $recipientCode,
        public readonly ?string $providerStatus,
        public readonly array $raw,
    ) {}
}
