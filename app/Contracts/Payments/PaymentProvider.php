<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use App\Support\Payments\PaymentInitialization;

interface PaymentProvider
{
    public function initialize(Payment $payment, string $callbackUrl): PaymentInitialization;

    public function webhookSignatureIsValid(string $payload, ?string $signature): bool;
}
