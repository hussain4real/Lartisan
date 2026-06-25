<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Models\Payment;
use App\Support\Payments\PaymentInitialization;
use App\Support\ProviderFailureLogger;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PaystackPaymentProvider implements PaymentProvider
{
    public function initialize(Payment $payment, string $callbackUrl): PaymentInitialization
    {
        $profile = $payment->artisanProfile()->firstOrFail();
        $owner = $profile->user()->firstOrFail();
        $metadata = json_encode([
            'artisan_profile_id' => $profile->id,
            'booking_id' => $payment->booking_id,
            'payment_id' => $payment->id,
            'purpose' => $payment->purpose->value,
        ], JSON_THROW_ON_ERROR);

        try {
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
        } catch (Throwable $throwable) {
            app(ProviderFailureLogger::class)->report('paystack', 'transaction-initialize', $throwable, [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
            ]);

            throw $throwable;
        }

        /** @var mixed $payload */
        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload) || ($payload['status'] ?? false) !== true) {
            app(ProviderFailureLogger::class)->report('paystack', 'transaction-initialize', 'Unexpected transaction initialization response.', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Paystack could not initialize the transaction.');
        }

        /** @var mixed $data */
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            app(ProviderFailureLogger::class)->report('paystack', 'transaction-initialize', 'Missing transaction initialization data.', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Paystack returned an invalid transaction initialization payload.');
        }

        $authorizationUrl = $data['authorization_url'] ?? null;
        $accessCode = $data['access_code'] ?? null;
        $reference = $data['reference'] ?? null;

        if (! is_string($authorizationUrl) || ! is_string($accessCode) || ! is_string($reference)) {
            app(ProviderFailureLogger::class)->report('paystack', 'transaction-initialize', 'Missing transaction checkout credentials.', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'status' => $response->status(),
            ]);

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
