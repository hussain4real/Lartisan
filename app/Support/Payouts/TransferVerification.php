<?php

namespace App\Support\Payouts;

class TransferVerification
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $reference,
        public readonly ?string $transferCode,
        public readonly string $providerStatus,
        public readonly ?string $failureReason,
        public readonly array $raw,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->providerStatus === 'success';
    }

    public function isFailed(): bool
    {
        return in_array($this->providerStatus, ['failed', 'reversed'], true);
    }

    public function isReversed(): bool
    {
        return $this->providerStatus === 'reversed';
    }

    public function requiresAction(): bool
    {
        return in_array($this->providerStatus, ['otp', 'otp_required', 'action_required'], true);
    }
}
