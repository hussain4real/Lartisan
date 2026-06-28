<?php

namespace App\Services\Payouts;

use App\Contracts\Payouts\PayoutProvider;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\PayoutAttempt;
use App\Support\Payouts\ActionRequiredPayoutException;
use App\Support\Payouts\BankAccountResolution;
use App\Support\Payouts\PayoutProviderException;
use App\Support\Payouts\TransferDispatch;
use App\Support\Payouts\TransferRecipient;
use App\Support\Payouts\TransferVerification;
use App\Support\Payouts\UncertainPayoutException;
use App\Support\ProviderFailureLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaystackPayoutProvider implements PayoutProvider
{
    public function __construct(
        private readonly ProviderFailureLogger $failureLogger,
    ) {}

    public function resolveBankAccount(PayoutAccount $account): BankAccountResolution
    {
        try {
            $response = $this->request()->get('/bank/resolve', [
                'account_number' => $account->account_number,
                'bank_code' => $account->bank_code,
            ]);
        } catch (Throwable $throwable) {
            $this->failureLogger->report('paystack', 'bank-resolve', $throwable, [
                'payout_account_id' => $account->id,
            ]);

            throw new PayoutProviderException('Paystack could not verify the bank account.', [
                'payout_account_id' => $account->id,
            ]);
        }

        $payload = $this->payload($response);
        $data = $this->arrayValue($payload['data'] ?? null);
        $message = $this->stringValue($payload['message'] ?? null);

        if (! $response->successful() || ($payload['status'] ?? false) !== true || $data === null) {
            return new BankAccountResolution(
                successful: false,
                accountName: null,
                accountNumber: $account->account_number,
                bankCode: $account->bank_code,
                bankName: $account->bank_name,
                providerStatus: $this->statusFrom($payload, 'failed'),
                failureReason: $message ?? 'Paystack could not resolve this account.',
                raw: $payload,
            );
        }

        return new BankAccountResolution(
            successful: true,
            accountName: $this->stringValue($data['account_name'] ?? null),
            accountNumber: $this->stringValue($data['account_number'] ?? null) ?? $account->account_number,
            bankCode: $this->stringValue($data['bank_code'] ?? null) ?? $account->bank_code,
            bankName: $this->stringValue($data['bank_name'] ?? null) ?? $account->bank_name,
            providerStatus: $this->statusFrom($payload, 'resolved'),
            failureReason: null,
            raw: $payload,
        );
    }

    public function createTransferRecipient(PayoutAccount $account): TransferRecipient
    {
        try {
            $response = $this->request()->post('/transferrecipient', [
                'account_number' => $account->account_number,
                'bank_code' => $account->bank_code,
                'currency' => 'NGN',
                'metadata' => [
                    'artisan_profile_id' => $account->artisan_profile_id,
                    'payout_account_id' => $account->id,
                ],
                'name' => $account->account_name,
                'type' => 'nuban',
            ]);
        } catch (Throwable $throwable) {
            $this->failureLogger->report('paystack', 'transfer-recipient-create', $throwable, [
                'payout_account_id' => $account->id,
            ]);

            throw new PayoutProviderException('Paystack could not create a transfer recipient.', [
                'payout_account_id' => $account->id,
            ]);
        }

        $payload = $this->payload($response);
        $data = $this->arrayValue($payload['data'] ?? null);
        $recipientCode = $this->stringValue($data['recipient_code'] ?? null);

        if (! $response->successful() || ($payload['status'] ?? false) !== true || $data === null || $recipientCode === null) {
            throw new PayoutProviderException(
                $this->stringValue($payload['message'] ?? null) ?? 'Paystack could not create a transfer recipient.',
                [
                    'payout_account_id' => $account->id,
                    'status' => $response->status(),
                ],
            );
        }

        return new TransferRecipient(
            recipientCode: $recipientCode,
            providerStatus: $this->statusFrom($data, 'active'),
            raw: $payload,
        );
    }

    public function initiateTransfer(Payout $payout, PayoutAttempt $attempt): TransferDispatch
    {
        $account = $payout->payoutAccount()->firstOrFail();
        $reference = $attempt->provider_reference ?? $this->transferReference($payout, $attempt);

        try {
            $response = $this->request()->post('/transfer', [
                'amount' => $payout->amount,
                'reason' => 'Lartisan payout #'.$payout->id,
                'recipient' => $account->recipient_code,
                'reference' => $reference,
                'source' => 'balance',
            ]);
        } catch (ConnectionException $throwable) {
            $this->failureLogger->report('paystack', 'transfer-initiate', $throwable, [
                'payout_id' => $payout->id,
                'reference' => $reference,
            ]);

            throw new UncertainPayoutException('Paystack transfer dispatch returned no conclusive response.', [
                'payout_id' => $payout->id,
                'reference' => $reference,
            ]);
        } catch (Throwable $throwable) {
            $this->failureLogger->report('paystack', 'transfer-initiate', $throwable, [
                'payout_id' => $payout->id,
                'reference' => $reference,
            ]);

            throw new UncertainPayoutException('Paystack transfer dispatch failed before a conclusive response.', [
                'payout_id' => $payout->id,
                'reference' => $reference,
            ]);
        }

        $payload = $this->payload($response);
        $data = $this->arrayValue($payload['data'] ?? null);
        $message = $this->stringValue($payload['message'] ?? null);
        $providerStatus = $this->statusFrom($data ?? $payload, 'pending');

        if ($this->requiresManualAction($payload)) {
            throw new ActionRequiredPayoutException($message ?? 'Paystack requires manual transfer action.', [
                'payout_id' => $payout->id,
                'reference' => $reference,
            ]);
        }

        if (! $response->successful() || ($payload['status'] ?? false) !== true || $data === null) {
            throw new PayoutProviderException($message ?? 'Paystack could not initiate the transfer.', [
                'payout_id' => $payout->id,
                'reference' => $reference,
                'status' => $response->status(),
            ]);
        }

        return new TransferDispatch(
            reference: $this->stringValue($data['reference'] ?? null) ?? $reference,
            transferCode: $this->stringValue($data['transfer_code'] ?? null),
            providerStatus: $providerStatus,
            actionRequired: $this->requiresManualAction($data),
            failureReason: null,
            raw: $payload,
        );
    }

    public function verifyTransfer(string $reference): TransferVerification
    {
        try {
            $response = $this->request()->get('/transfer/verify/'.rawurlencode($reference));
        } catch (Throwable $throwable) {
            $this->failureLogger->report('paystack', 'transfer-verify', $throwable, [
                'reference' => $reference,
            ]);

            throw new PayoutProviderException('Paystack could not verify the transfer.', [
                'reference' => $reference,
            ]);
        }

        $payload = $this->payload($response);
        $data = $this->arrayValue($payload['data'] ?? null);

        if (! $response->successful() || ($payload['status'] ?? false) !== true || $data === null) {
            throw new PayoutProviderException(
                $this->stringValue($payload['message'] ?? null) ?? 'Paystack could not verify the transfer.',
                [
                    'reference' => $reference,
                    'status' => $response->status(),
                ],
            );
        }

        return new TransferVerification(
            reference: $this->stringValue($data['reference'] ?? null) ?? $reference,
            transferCode: $this->stringValue($data['transfer_code'] ?? null),
            providerStatus: $this->statusFrom($data, 'pending'),
            failureReason: $this->failureReasonFrom($data),
            raw: $payload,
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

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 100, throw: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        $result = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return trim((string) $value) ?: null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function statusFrom(array $payload, string $default): string
    {
        $status = $this->stringValue($payload['status'] ?? null);

        if ($status === null) {
            return $default;
        }

        return strtolower($status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiresManualAction(array $payload): bool
    {
        $status = $this->statusFrom($payload, '');
        $message = strtolower($this->stringValue($payload['message'] ?? null) ?? '');

        return in_array($status, ['otp', 'otp_required'], true)
            || str_contains($message, 'otp');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function failureReasonFrom(array $data): ?string
    {
        return $this->stringValue($data['failure_reason'] ?? null)
            ?? $this->stringValue($data['gateway_response'] ?? null)
            ?? $this->stringValue($data['reason'] ?? null);
    }

    private function transferReference(Payout $payout, PayoutAttempt $attempt): string
    {
        return 'payout-'.$payout->id.'-attempt-'.$attempt->attempt_number;
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
