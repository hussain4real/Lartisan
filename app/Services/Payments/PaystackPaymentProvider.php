<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Models\Payment;
use App\Support\Payments\PaymentInitialization;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentProvider implements PaymentProvider
{
    public function initialize(Payment $payment, string $callbackUrl): PaymentInitialization
    {
        $profile = $payment->artisanProfile()->firstOrFail();
        $owner = $profile->user()->firstOrFail();
        $metadata = json_encode([
            'artisan_profile_id' => $profile->id,
            'payment_id' => $payment->id,
            'purpose' => $payment->purpose->value,
        ], JSON_THROW_ON_ERROR);

        $response = Http::baseUrl($this->baseUrl())
            ->withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 100, throw: false)
            ->post('/transaction/initialize', [
                'amount' => (string) $payment->amount,
                'callback_url' => $callbackUrl,
                'currency' => $payment->currency_code,
                'email' => $owner->email,
                'metadata' => $metadata,
                'reference' => $payment->reference,
            ]);

        /** @var mixed $payload */
        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload) || ($payload['status'] ?? false) !== true) {
            throw new RuntimeException('Paystack could not initialize the transaction.');
        }

        /** @var mixed $data */
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('Paystack returned an invalid transaction initialization payload.');
        }

        $authorizationUrl = $data['authorization_url'] ?? null;
        $accessCode = $data['access_code'] ?? null;
        $reference = $data['reference'] ?? null;

        if (! is_string($authorizationUrl) || ! is_string($accessCode) || ! is_string($reference)) {
            throw new RuntimeException('Paystack did not return checkout credentials.');
        }

        /** @var array<string, mixed> $raw */
        $raw = $payload;

        return new PaymentInitialization(
            authorizationUrl: $authorizationUrl,
            accessCode: $accessCode,
            reference: $reference,
            raw: $raw,
        );
    }

    public function webhookSignatureIsValid(string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->secretKey() === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha512', $payload, $this->secretKey()),
            $signature,
        );
    }

    private function secretKey(): string
    {
        $secretKey = config('services.paystack.secret_key');

        return is_string($secretKey) ? $secretKey : '';
    }

    private function baseUrl(): string
    {
        $baseUrl = config('services.paystack.payment_url', 'https://api.paystack.co');

        return is_string($baseUrl) ? rtrim($baseUrl, '/') : 'https://api.paystack.co';
    }
}
