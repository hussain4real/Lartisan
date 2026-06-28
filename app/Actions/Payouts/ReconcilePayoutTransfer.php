<?php

namespace App\Actions\Payouts;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProviderWebhookEventStatus;
use App\Enums\SupportCasePriority;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Models\ProviderWebhookEvent;
use App\Support\Payouts\TransferVerification;
use Illuminate\Support\Facades\DB;

class ReconcilePayoutTransfer
{
    public function __construct(
        private readonly ReleaseFailedPayoutBalance $releaseFailedPayoutBalance,
        private readonly CreatePayoutExceptionCase $createPayoutExceptionCase,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(
        Payout $payout,
        TransferVerification $verification,
        ?ProviderWebhookEvent $webhookEvent = null,
    ): Payout {
        if ($verification->requiresAction()) {
            return $this->markActionRequired($payout, $verification, $webhookEvent);
        }

        if ($verification->isSuccessful()) {
            return $this->markSuccessful($payout, $verification, $webhookEvent);
        }

        if ($verification->isReversed()) {
            return $this->markReversed($payout, $verification, $webhookEvent);
        }

        if ($verification->isFailed()) {
            return $this->markFailed($payout, $verification, $webhookEvent);
        }

        return $this->markPending($payout, $verification, $webhookEvent);
    }

    private function markSuccessful(
        Payout $payout,
        TransferVerification $verification,
        ?ProviderWebhookEvent $webhookEvent,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $verification, $webhookEvent): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $attempt = $this->attemptFor($lockedPayout, $verification);
            $before = ['status' => $lockedPayout->status->value];

            if ($attempt instanceof PayoutAttempt) {
                $attempt->forceFill([
                    'last_reconciled_at' => now(),
                    'processed_at' => $attempt->processed_at ?? now(),
                    'provider_payload' => $verification->raw,
                    'provider_reference' => $verification->reference,
                    'provider_status' => $verification->providerStatus,
                    'provider_transfer_code' => $verification->transferCode,
                    'status' => PayoutAttemptStatus::Successful,
                ])->save();
            }

            if ($lockedPayout->status !== PayoutStatus::Paid) {
                $lockedPayout->forceFill([
                    'failed_at' => null,
                    'failure_reason' => null,
                    'next_retry_at' => null,
                    'paid_at' => $lockedPayout->paid_at ?? now(),
                    'provider_reference' => $verification->reference,
                    'provider_status' => $verification->providerStatus,
                    'provider_transfer_code' => $verification->transferCode,
                    'reconciled_at' => now(),
                    'status' => PayoutStatus::Paid,
                ])->save();

                $this->recordAuditLog->handle(
                    actor: null,
                    action: 'payout.paid',
                    subject: $lockedPayout,
                    before: $before,
                    after: ['status' => $lockedPayout->status->value],
                );
            }

            $this->finishWebhookEvent($webhookEvent, ProviderWebhookEventStatus::Processed);

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function markFailed(
        Payout $payout,
        TransferVerification $verification,
        ?ProviderWebhookEvent $webhookEvent,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $verification, $webhookEvent): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $attempt = $this->attemptFor($lockedPayout, $verification);
            $latestAttemptNumber = $lockedPayout->attempts()->max('attempt_number');
            $attemptNumber = $attempt instanceof PayoutAttempt
                ? $attempt->attempt_number
                : (is_numeric($latestAttemptNumber) ? (int) $latestAttemptNumber : 0);
            $isTerminalFailure = $attemptNumber >= $this->maxAttempts();
            $reason = $verification->failureReason ?? 'Provider transfer failed.';
            $before = ['status' => $lockedPayout->status->value];

            if ($attempt instanceof PayoutAttempt) {
                $attempt->forceFill([
                    'failure_reason' => $reason,
                    'last_reconciled_at' => now(),
                    'processed_at' => now(),
                    'provider_payload' => $verification->raw,
                    'provider_reference' => $verification->reference,
                    'provider_status' => $verification->providerStatus,
                    'provider_transfer_code' => $verification->transferCode,
                    'status' => PayoutAttemptStatus::Failed,
                ])->save();
            }

            $lockedPayout->forceFill([
                'failed_at' => $isTerminalFailure ? now() : null,
                'failure_reason' => $reason,
                'next_retry_at' => $isTerminalFailure ? null : now()->addMinutes($this->retryDelayMinutes()),
                'provider_status' => $verification->providerStatus,
                'reconciled_at' => now(),
                'status' => $isTerminalFailure ? PayoutStatus::Failed : PayoutStatus::Retrying,
            ])->save();

            if ($isTerminalFailure) {
                $this->releaseFailedPayoutBalance->handle(
                    payout: $lockedPayout,
                    immutableReference: 'payout-'.$lockedPayout->id.'-failed-release',
                    description: 'Released failed payout debit',
                    metadata: [
                        'attempt_number' => $attemptNumber,
                        'failure_reason' => $reason,
                    ],
                );
            }

            $this->recordAuditLog->handle(
                actor: null,
                action: $isTerminalFailure ? 'payout.failed' : 'payout.failed_attempt',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );
            $this->finishWebhookEvent($webhookEvent, ProviderWebhookEventStatus::Processed);

            return $lockedPayout->refresh();
        }, attempts: 3);

        if ($updatedPayout->status === PayoutStatus::Failed) {
            $this->createPayoutExceptionCase->handle(
                payout: $updatedPayout,
                subject: 'Payout transfer failed',
                description: $updatedPayout->failure_reason,
                priority: SupportCasePriority::High,
                metadata: ['provider_verification' => $verification->raw],
            );
        }

        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function markReversed(
        Payout $payout,
        TransferVerification $verification,
        ?ProviderWebhookEvent $webhookEvent,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $verification, $webhookEvent): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $attempt = $this->attemptFor($lockedPayout, $verification);
            $reason = $verification->failureReason ?? 'Provider transfer was reversed.';
            $before = ['status' => $lockedPayout->status->value];

            if ($attempt instanceof PayoutAttempt) {
                $attempt->forceFill([
                    'failure_reason' => $reason,
                    'last_reconciled_at' => now(),
                    'processed_at' => now(),
                    'provider_payload' => $verification->raw,
                    'provider_status' => $verification->providerStatus,
                    'provider_transfer_code' => $verification->transferCode,
                    'status' => PayoutAttemptStatus::Failed,
                ])->save();
            }

            $targetStatus = $lockedPayout->status === PayoutStatus::Paid
                ? PayoutStatus::Adjusted
                : PayoutStatus::Failed;

            $lockedPayout->forceFill([
                'failed_at' => now(),
                'failure_reason' => $reason,
                'next_retry_at' => null,
                'provider_status' => $verification->providerStatus,
                'reconciled_at' => now(),
                'status' => $targetStatus,
            ])->save();

            $this->releaseFailedPayoutBalance->handle(
                payout: $lockedPayout,
                immutableReference: $targetStatus === PayoutStatus::Adjusted
                    ? 'payout-'.$lockedPayout->id.'-reversal-credit'
                    : 'payout-'.$lockedPayout->id.'-failed-release',
                description: $targetStatus === PayoutStatus::Adjusted
                    ? 'Credited reversed payout transfer'
                    : 'Released reversed payout debit',
                metadata: [
                    'failure_reason' => $reason,
                    'provider_status' => $verification->providerStatus,
                ],
            );

            $this->recordAuditLog->handle(
                actor: null,
                action: 'payout.reversed',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );
            $this->finishWebhookEvent($webhookEvent, ProviderWebhookEventStatus::Processed);

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->createPayoutExceptionCase->handle(
            payout: $updatedPayout,
            subject: 'Payout transfer reversed',
            description: $updatedPayout->failure_reason,
            priority: SupportCasePriority::Urgent,
            metadata: ['provider_verification' => $verification->raw],
        );
        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function markActionRequired(
        Payout $payout,
        TransferVerification $verification,
        ?ProviderWebhookEvent $webhookEvent,
    ): Payout {
        $updatedPayout = DB::transaction(function () use ($payout, $verification, $webhookEvent): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $attempt = $this->attemptFor($lockedPayout, $verification);
            $reason = $verification->failureReason ?? 'Provider transfer requires manual action.';
            $before = ['status' => $lockedPayout->status->value];

            if ($attempt instanceof PayoutAttempt) {
                $attempt->forceFill([
                    'failure_reason' => $reason,
                    'last_reconciled_at' => now(),
                    'provider_payload' => $verification->raw,
                    'provider_status' => $verification->providerStatus,
                    'status' => PayoutAttemptStatus::ActionRequired,
                ])->save();
            }

            $lockedPayout->forceFill([
                'failure_reason' => $reason,
                'next_retry_at' => null,
                'provider_status' => $verification->providerStatus,
                'reconciled_at' => now(),
                'status' => PayoutStatus::InReview,
            ])->save();

            $this->recordAuditLog->handle(
                actor: null,
                action: 'payout.action_required',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
                reason: $reason,
            );
            $this->finishWebhookEvent($webhookEvent, ProviderWebhookEventStatus::Processed);

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->createPayoutExceptionCase->handle(
            payout: $updatedPayout,
            subject: 'Payout transfer requires finance action',
            description: $updatedPayout->failure_reason,
            priority: SupportCasePriority::High,
            metadata: ['provider_verification' => $verification->raw],
        );
        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function markPending(
        Payout $payout,
        TransferVerification $verification,
        ?ProviderWebhookEvent $webhookEvent,
    ): Payout {
        return DB::transaction(function () use ($payout, $verification, $webhookEvent): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $attempt = $this->attemptFor($lockedPayout, $verification);

            if ($attempt instanceof PayoutAttempt) {
                $attempt->forceFill([
                    'last_reconciled_at' => now(),
                    'provider_payload' => $verification->raw,
                    'provider_status' => $verification->providerStatus,
                    'status' => PayoutAttemptStatus::Uncertain,
                ])->save();
            }

            $lockedPayout->forceFill([
                'next_retry_at' => now()->addMinutes($this->retryDelayMinutes()),
                'provider_status' => $verification->providerStatus,
                'reconciled_at' => now(),
                'status' => PayoutStatus::Processing,
            ])->save();

            $this->finishWebhookEvent($webhookEvent, ProviderWebhookEventStatus::Processed);

            return $lockedPayout->refresh();
        }, attempts: 3);
    }

    private function attemptFor(Payout $payout, TransferVerification $verification): ?PayoutAttempt
    {
        $attempt = $payout->attempts()
            ->where(function ($query) use ($verification): void {
                $query->where('provider_reference', $verification->reference);

                if ($verification->transferCode !== null) {
                    $query->orWhere('provider_transfer_code', $verification->transferCode);
                }
            })
            ->lockForUpdate()
            ->first();

        if ($attempt instanceof PayoutAttempt) {
            return $attempt;
        }

        return $payout->attempts()->latest('attempt_number')->lockForUpdate()->first();
    }

    private function finishWebhookEvent(
        ?ProviderWebhookEvent $webhookEvent,
        ProviderWebhookEventStatus $status,
        ?string $failureReason = null,
    ): void {
        if (! $webhookEvent instanceof ProviderWebhookEvent) {
            return;
        }

        $webhookEvent->forceFill([
            'failure_reason' => $failureReason,
            'processed_at' => now(),
            'status' => $status,
        ])->save();
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
