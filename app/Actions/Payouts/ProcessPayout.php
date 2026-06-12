<?php

namespace App\Actions\Payouts;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Payments\PostWalletLedgerEntry;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessPayout
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
    ) {}

    /**
     * @param  array<string, mixed>|null  $providerPayload
     */
    public function handle(
        Payout $payout,
        User $processor,
        bool $successful = true,
        ?string $providerReference = null,
        ?string $failureReason = null,
        ?array $providerPayload = null,
        int $maxAttempts = 3,
    ): Payout {
        return DB::transaction(function () use (
            $payout,
            $processor,
            $successful,
            $providerReference,
            $failureReason,
            $providerPayload,
            $maxAttempts,
        ): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->authorize($lockedPayout, $processor);

            if (! in_array($lockedPayout->status, [PayoutStatus::Approved, PayoutStatus::Processing, PayoutStatus::Retrying], true)) {
                throw new InvalidArgumentException('Only approved or retrying payouts can be processed.');
            }

            $latestAttemptNumber = $lockedPayout->attempts()->max('attempt_number');
            $attemptNumber = (is_numeric($latestAttemptNumber) ? (int) $latestAttemptNumber : 0) + 1;
            $attempt = PayoutAttempt::query()->create([
                'payout_id' => $lockedPayout->id,
                'attempt_number' => $attemptNumber,
                'status' => PayoutAttemptStatus::Processing,
                'provider_reference' => $providerReference,
                'provider_payload' => $providerPayload,
            ]);

            $before = ['status' => $lockedPayout->status->value];
            $lockedPayout->forceFill([
                'processed_by' => $processor->id,
                'processing_at' => now(),
                'status' => PayoutStatus::Processing,
            ])->save();

            if ($successful) {
                $attempt->forceFill([
                    'processed_at' => now(),
                    'provider_reference' => $providerReference,
                    'status' => PayoutAttemptStatus::Successful,
                ])->save();

                $lockedPayout->forceFill([
                    'paid_at' => now(),
                    'provider_reference' => $providerReference,
                    'provider_transfer_code' => $providerReference,
                    'status' => PayoutStatus::Paid,
                ])->save();
            } else {
                $isTerminalFailure = $attemptNumber >= $maxAttempts;

                $attempt->forceFill([
                    'failure_reason' => $failureReason ?? 'Provider transfer failed.',
                    'processed_at' => now(),
                    'status' => PayoutAttemptStatus::Failed,
                ])->save();

                $lockedPayout->forceFill([
                    'failed_at' => $isTerminalFailure ? now() : null,
                    'failure_reason' => $failureReason ?? 'Provider transfer failed.',
                    'status' => $isTerminalFailure ? PayoutStatus::Failed : PayoutStatus::Retrying,
                ])->save();

                if ($isTerminalFailure) {
                    $this->releaseFailedPayoutBalance($lockedPayout, $attemptNumber, $failureReason);
                }
            }

            $this->recordAuditLog->handle(
                actor: $processor,
                action: $successful ? 'payout.paid' : 'payout.failed_attempt',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $failureReason,
            );

            return $lockedPayout->refresh();
        }, attempts: 3);
    }

    private function releaseFailedPayoutBalance(Payout $payout, int $attemptNumber, ?string $failureReason): void
    {
        $hasPayoutDebit = WalletLedgerEntry::query()
            ->where('source_type', $payout->getMorphClass())
            ->where('source_id', $payout->id)
            ->where('type', WalletLedgerEntryType::PayoutDebit)
            ->exists();

        $hasReleaseCredit = WalletLedgerEntry::query()
            ->where('source_type', $payout->getMorphClass())
            ->where('source_id', $payout->id)
            ->where('type', WalletLedgerEntryType::AdjustmentCredit)
            ->where('immutable_reference', 'payout-'.$payout->id.'-failed-release')
            ->exists();

        if (! $hasPayoutDebit || $hasReleaseCredit) {
            return;
        }

        $this->postWalletLedgerEntry->handle(
            wallet: $payout->wallet()->firstOrFail(),
            type: WalletLedgerEntryType::AdjustmentCredit,
            direction: WalletLedgerDirection::Credit,
            amount: $payout->amount,
            source: $payout,
            immutableReference: 'payout-'.$payout->id.'-failed-release',
            description: 'Released failed payout debit',
            metadata: [
                'attempt_number' => $attemptNumber,
                'failure_reason' => $failureReason,
            ],
        );
    }

    private function authorize(Payout $payout, User $processor): void
    {
        $profile = $payout->artisanProfile()->firstOrFail();

        if ($processor->can(PlatformPermission::ManagePayouts->value)
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($processor)->exists()) {
            return;
        }

        throw new AuthorizationException('You cannot process this payout.');
    }
}
