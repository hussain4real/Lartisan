<?php

namespace App\Actions\Payouts;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Contracts\Payouts\PayoutProvider;
use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutStatus;
use App\Enums\SupportCasePriority;
use App\Enums\WalletLedgerEntryType;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Support\Payouts\ActionRequiredPayoutException;
use App\Support\Payouts\PayoutProviderException;
use App\Support\Payouts\TransferDispatch;
use App\Support\Payouts\UncertainPayoutException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DispatchPayoutTransfer
{
    public function __construct(
        private readonly PayoutProvider $payoutProvider,
        private readonly VerifyPayoutAccount $verifyPayoutAccount,
        private readonly ReleaseFailedPayoutBalance $releaseFailedPayoutBalance,
        private readonly CreatePayoutExceptionCase $createPayoutExceptionCase,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Payout $payout, ?User $actor = null, ?PayoutBatch $batch = null): Payout
    {
        $account = $payout->payoutAccount()->firstOrFail();

        if ($account->status !== PayoutAccountStatus::Verified || $account->recipient_code === null) {
            $account = $this->verifyPayoutAccount->handle($account);
        }

        if ($account->status !== PayoutAccountStatus::Verified || $account->recipient_code === null) {
            return $this->moveToReview(
                payout: $payout,
                actor: $actor,
                reason: $account->verification_failure_reason ?? 'Verified transfer recipient is required before automated payout dispatch.',
                providerStatus: $account->verification_provider_status,
                batch: $batch,
            );
        }

        [$preparedPayout, $attempt] = $this->prepareAttempt($payout, $actor, $batch);

        if (! $attempt instanceof PayoutAttempt) {
            return $preparedPayout->refresh();
        }

        try {
            $dispatch = $this->payoutProvider->initiateTransfer($preparedPayout, $attempt);
        } catch (ActionRequiredPayoutException $exception) {
            return $this->markActionRequired($preparedPayout, $attempt, $actor, $exception->getMessage(), $exception->context);
        } catch (UncertainPayoutException $exception) {
            return $this->markUncertain($preparedPayout, $attempt, $actor, $exception->getMessage(), $exception->context);
        } catch (PayoutProviderException $exception) {
            return $this->markProviderFailure($preparedPayout, $attempt, $actor, $exception->getMessage(), $exception->context);
        }

        if ($dispatch->actionRequired) {
            return $this->markActionRequired(
                payout: $preparedPayout,
                attempt: $attempt,
                actor: $actor,
                reason: $dispatch->failureReason ?? 'Paystack requires manual transfer action.',
                context: $dispatch->raw,
            );
        }

        return $this->markDispatched($preparedPayout, $attempt, $actor, $dispatch);
    }

    /**
     * @return array{0: Payout, 1: PayoutAttempt|null}
     */
    private function prepareAttempt(Payout $payout, ?User $actor, ?PayoutBatch $batch): array
    {
        return DB::transaction(function () use ($payout, $actor, $batch): array {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPayout->status, [PayoutStatus::Approved, PayoutStatus::Retrying], true)) {
                throw new InvalidArgumentException('Only approved or retrying payouts can be dispatched.');
            }

            if (! $this->hasReservedDebit($lockedPayout)) {
                return [
                    $this->moveToReview(
                        payout: $lockedPayout,
                        actor: $actor,
                        reason: 'Approved payout is missing its reserved wallet debit.',
                        providerStatus: 'missing_debit',
                        batch: $batch,
                    ),
                    null,
                ];
            }

            $latestAttemptNumber = $lockedPayout->attempts()->max('attempt_number');
            $attemptNumber = (is_numeric($latestAttemptNumber) ? (int) $latestAttemptNumber : 0) + 1;
            $reference = $this->transferReference($lockedPayout, $attemptNumber);
            $before = ['status' => $lockedPayout->status->value];

            $attempt = PayoutAttempt::query()->create([
                'payout_id' => $lockedPayout->id,
                'attempt_number' => $attemptNumber,
                'status' => PayoutAttemptStatus::Processing,
                'provider_reference' => $reference,
                'provider_status' => 'dispatching',
                'provider_payload' => ['source' => 'payout_automation'],
            ]);

            $lockedPayout->forceFill([
                'payout_batch_id' => $batch instanceof PayoutBatch ? $batch->id : $lockedPayout->payout_batch_id,
                'processed_by' => $actor?->id,
                'processing_at' => now(),
                'provider_reference' => $reference,
                'provider_status' => 'dispatching',
                'status' => PayoutStatus::Processing,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'payout.dispatch_started',
                subject: $lockedPayout,
                before: $before,
                after: [
                    'attempt_number' => $attemptNumber,
                    'provider_reference' => $reference,
                    'status' => $lockedPayout->status->value,
                ],
            );

            return [$lockedPayout->refresh(), $attempt->refresh()];
        }, attempts: 3);
    }

    private function markDispatched(
        Payout $payout,
        PayoutAttempt $attempt,
        ?User $actor,
        TransferDispatch $dispatch,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $attempt, $actor, $dispatch): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $lockedAttempt = PayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $lockedAttempt->forceFill([
                'provider_payload' => $dispatch->raw,
                'provider_reference' => $dispatch->reference,
                'provider_status' => $dispatch->providerStatus,
                'provider_transfer_code' => $dispatch->transferCode,
            ])->save();

            $lockedPayout->forceFill([
                'failure_reason' => null,
                'next_retry_at' => null,
                'provider_reference' => $dispatch->reference,
                'provider_status' => $dispatch->providerStatus,
                'provider_transfer_code' => $dispatch->transferCode,
                'status' => PayoutStatus::Processing,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'payout.dispatched',
                subject: $lockedPayout,
                after: [
                    'provider_reference' => $dispatch->reference,
                    'provider_status' => $dispatch->providerStatus,
                    'provider_transfer_code' => $dispatch->transferCode,
                    'status' => $lockedPayout->status->value,
                ],
            );

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function markActionRequired(
        Payout $payout,
        PayoutAttempt $attempt,
        ?User $actor,
        string $reason,
        array $context,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $attempt, $actor, $reason, $context): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $lockedAttempt = PayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $lockedPayout->status->value];

            $lockedAttempt->forceFill([
                'failure_reason' => $reason,
                'processed_at' => now(),
                'provider_payload' => [
                    ...($lockedAttempt->provider_payload ?? []),
                    'exception' => $context,
                ],
                'provider_status' => 'action_required',
                'status' => PayoutAttemptStatus::ActionRequired,
            ])->save();

            $lockedPayout->forceFill([
                'failure_reason' => $reason,
                'next_retry_at' => null,
                'provider_status' => 'action_required',
                'status' => PayoutStatus::InReview,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'payout.action_required',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->createPayoutExceptionCase->handle(
            payout: $updatedPayout,
            subject: 'Payout transfer requires finance action',
            description: $reason,
            priority: SupportCasePriority::High,
            metadata: ['exception' => $context],
        );
        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function markUncertain(
        Payout $payout,
        PayoutAttempt $attempt,
        ?User $actor,
        string $reason,
        array $context,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $attempt, $actor, $reason, $context): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $lockedAttempt = PayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $lockedAttempt->forceFill([
                'failure_reason' => $reason,
                'provider_payload' => [
                    ...($lockedAttempt->provider_payload ?? []),
                    'exception' => $context,
                ],
                'provider_status' => 'uncertain',
                'status' => PayoutAttemptStatus::Uncertain,
            ])->save();

            $lockedPayout->forceFill([
                'failure_reason' => $reason,
                'provider_status' => 'uncertain',
                'status' => PayoutStatus::Processing,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'payout.dispatch_uncertain',
                subject: $lockedPayout,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->createPayoutExceptionCase->handle(
            payout: $updatedPayout,
            subject: 'Payout transfer needs reconciliation',
            description: $reason,
            priority: SupportCasePriority::Normal,
            metadata: ['exception' => $context],
        );
        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function markProviderFailure(
        Payout $payout,
        PayoutAttempt $attempt,
        ?User $actor,
        string $reason,
        array $context,
    ): Payout {
        $maxAttempts = $this->maxAttempts();
        $isTerminalFailure = $attempt->attempt_number >= $maxAttempts;

        $updatedPayout = DB::transaction(function () use ($payout, $attempt, $actor, $reason, $context, $isTerminalFailure): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $lockedAttempt = PayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $lockedPayout->status->value];

            $lockedAttempt->forceFill([
                'failure_reason' => $reason,
                'processed_at' => now(),
                'provider_payload' => [
                    ...($lockedAttempt->provider_payload ?? []),
                    'exception' => $context,
                ],
                'provider_status' => 'failed',
                'status' => PayoutAttemptStatus::Failed,
            ])->save();

            $lockedPayout->forceFill([
                'failed_at' => $isTerminalFailure ? now() : null,
                'failure_reason' => $reason,
                'next_retry_at' => $isTerminalFailure ? null : now()->addMinutes($this->retryDelayMinutes()),
                'provider_status' => 'failed',
                'status' => $isTerminalFailure ? PayoutStatus::Failed : PayoutStatus::Retrying,
            ])->save();

            if ($isTerminalFailure) {
                $this->releaseFailedPayoutBalance->handle(
                    payout: $lockedPayout,
                    immutableReference: 'payout-'.$lockedPayout->id.'-failed-release',
                    description: 'Released failed payout debit',
                    metadata: [
                        'attempt_number' => $lockedAttempt->attempt_number,
                        'failure_reason' => $reason,
                    ],
                );
            }

            $this->recordAuditLog->handle(
                actor: $actor,
                action: $isTerminalFailure ? 'payout.failed' : 'payout.failed_attempt',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );

            return $lockedPayout->refresh();
        }, attempts: 3);

        if ($isTerminalFailure) {
            $this->createPayoutExceptionCase->handle(
                payout: $updatedPayout,
                subject: 'Payout transfer failed',
                description: $reason,
                priority: SupportCasePriority::High,
                metadata: ['exception' => $context],
            );
        }

        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function moveToReview(
        Payout $payout,
        ?User $actor,
        string $reason,
        ?string $providerStatus,
        ?PayoutBatch $batch,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $actor, $reason, $providerStatus, $batch): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $lockedPayout->status->value];

            $lockedPayout->forceFill([
                'failure_reason' => $reason,
                'next_retry_at' => null,
                'payout_batch_id' => $batch instanceof PayoutBatch ? $batch->id : $lockedPayout->payout_batch_id,
                'provider_status' => $providerStatus ?? 'action_required',
                'status' => PayoutStatus::InReview,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'payout.action_required',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->createPayoutExceptionCase->handle(
            payout: $updatedPayout,
            subject: 'Payout transfer requires finance action',
            description: $reason,
            priority: SupportCasePriority::High,
        );
        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function hasReservedDebit(Payout $payout): bool
    {
        return WalletLedgerEntry::query()
            ->where('source_type', $payout->getMorphClass())
            ->where('source_id', $payout->id)
            ->where('type', WalletLedgerEntryType::PayoutDebit)
            ->exists();
    }

    private function transferReference(Payout $payout, int $attemptNumber): string
    {
        return 'payout-'.$payout->id.'-attempt-'.$attemptNumber;
    }

    private function maxAttempts(): int
    {
        $maxAttempts = config('lartisan.payouts.max_attempts', 3);

        return max(1, is_numeric($maxAttempts) ? (int) $maxAttempts : 3);
    }

    private function retryDelayMinutes(): int
    {
        $retryDelay = config('lartisan.payouts.retry_delay_minutes', 60);

        return max(1, is_numeric($retryDelay) ? (int) $retryDelay : 60);
    }
}
