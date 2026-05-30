<?php

namespace App\Support\Payments;

final readonly class PaymentInitialization
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $authorizationUrl,
        public string $accessCode,
        public string $reference,
        public array $raw,
    ) {}
}
