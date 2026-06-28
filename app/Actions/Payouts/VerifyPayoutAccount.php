<?php

namespace App\Actions\Payouts;

use App\Contracts\Payouts\PayoutProvider;
use App\Enums\PayoutAccountStatus;
use App\Models\PayoutAccount;
use App\Support\Payouts\PayoutProviderException;
use Illuminate\Support\Facades\DB;

class VerifyPayoutAccount
{
    public function __construct(
        private readonly PayoutProvider $payoutProvider,
    ) {}

    public function handle(PayoutAccount $account): PayoutAccount
    {
        if ($account->status === PayoutAccountStatus::Verified && $account->recipient_code !== null) {
            return $account->refresh();
        }

        try {
            $resolution = $this->payoutProvider->resolveBankAccount($account);
        } catch (PayoutProviderException $exception) {
            return $this->recordProviderException($account, $exception);
        }

        if (! $resolution->successful) {
            return DB::transaction(function () use ($account, $resolution): PayoutAccount {
                $lockedAccount = PayoutAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

                $lockedAccount->forceFill([
                    'status' => PayoutAccountStatus::Rejected,
                    'verification_checked_at' => now(),
                    'verification_failure_reason' => $resolution->failureReason ?? 'Provider could not verify the bank account.',
                    'verification_provider_status' => $resolution->providerStatus,
                    'metadata' => [
                        ...($lockedAccount->metadata ?? []),
                        'bank_resolution' => $resolution->raw,
                    ],
                ])->save();

                return $lockedAccount->refresh();
            }, attempts: 3);
        }

        $account->forceFill([
            'account_name' => $resolution->accountName ?? $account->account_name,
            'bank_code' => $resolution->bankCode ?? $account->bank_code,
            'bank_name' => $resolution->bankName ?? $account->bank_name,
            'verification_checked_at' => now(),
            'verification_failure_reason' => null,
            'verification_provider_status' => $resolution->providerStatus,
            'metadata' => [
                ...($account->metadata ?? []),
                'bank_resolution' => $resolution->raw,
            ],
        ])->save();

        try {
            $recipient = $this->payoutProvider->createTransferRecipient($account->refresh());
        } catch (PayoutProviderException $exception) {
            return $this->recordProviderException($account, $exception);
        }

        return DB::transaction(function () use ($account, $recipient): PayoutAccount {
            $lockedAccount = PayoutAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $lockedAccount->forceFill([
                'recipient_code' => $recipient->recipientCode,
                'recipient_registered_at' => now(),
                'status' => PayoutAccountStatus::Verified,
                'verification_failure_reason' => null,
                'verification_provider_status' => $recipient->providerStatus,
                'verified_at' => now(),
                'metadata' => [
                    ...($lockedAccount->metadata ?? []),
                    'transfer_recipient' => $recipient->raw,
                ],
            ])->save();

            return $lockedAccount->refresh();
        }, attempts: 3);
    }

    private function recordProviderException(PayoutAccount $account, PayoutProviderException $exception): PayoutAccount
    {
        return DB::transaction(function () use ($account, $exception): PayoutAccount {
            $lockedAccount = PayoutAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $lockedAccount->forceFill([
                'verification_checked_at' => now(),
                'verification_failure_reason' => $exception->getMessage(),
                'verification_provider_status' => 'provider_error',
                'metadata' => [
                    ...($lockedAccount->metadata ?? []),
                    'provider_exception' => $exception->context,
                ],
            ])->save();

            return $lockedAccount->refresh();
        }, attempts: 3);
    }
}
