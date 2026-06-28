<?php

namespace App\Actions\Payouts;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\SupportCasePriority;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\PayoutAttempt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessPayout
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
        private readonly ReleaseFailedPayoutBalance $releaseFailedPayoutBalance,
        private readonly CreatePayoutExceptionCase $createPayoutExceptionCase,
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
        $updatedPayout = DB::transaction(function () use (
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

            if (! in_array($lockedPayout->status, [PayoutStatus::Approved, PayoutStatus::InReview, PayoutStatus::Processing, PayoutStatus::Retrying], true)) {
                throw new InvalidArgumentException('Only approved or retrying payouts can be processed.');
            }

            if ($successful && ! $lockedPayout->hasReservedDebit()) {
                throw new InvalidArgumentException('Successful payout processing requires a reserved wallet debit. Re-approve the payout before marking it paid.');
            }

            $latestAttemptNumber = $lockedPayout->attempts()->max('attempt_number');
            $attemptNumber = (is_numeric($latestAttemptNumber) ? (int) $latestAttemptNumber : 0) + 1;
            $attempt = PayoutAttempt::query()->create([
                'payout_id' => $lockedPayout->id,
                'attempt_number' => $attemptNumber,
                'status' => PayoutAttemptStatus::Processing,
                'provider_reference' => $providerReference,
                'provider_status' => $successful ? 'manual_success' : 'manual_failure',
                'provider_transfer_code' => $providerReference,
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
                    'provider_status' => 'manual_success',
                    'provider_transfer_code' => $providerReference,
                    'status' => PayoutAttemptStatus::Successful,
                ])->save();

                $lockedPayout->forceFill([
                    'paid_at' => now(),
                    'provider_reference' => $providerReference,
                    'provider_status' => 'manual_success',
                    'provider_transfer_code' => $providerReference,
                    'reconciled_at' => now(),
                    'status' => PayoutStatus::Paid,
                ])->save();
            } else {
                $isTerminalFailure = $attemptNumber >= $maxAttempts;

                $attempt->forceFill([
                    'failure_reason' => $failureReason ?? 'Provider transfer failed.',
                    'processed_at' => now(),
                    'provider_status' => 'manual_failure',
                    'status' => PayoutAttemptStatus::Failed,
                ])->save();

                $lockedPayout->forceFill([
                    'failed_at' => $isTerminalFailure ? now() : null,
                    'failure_reason' => $failureReason ?? 'Provider transfer failed.',
                    'next_retry_at' => $isTerminalFailure ? null : now()->addMinutes($this->retryDelayMinutes()),
                    'provider_status' => 'manual_failure',
                    'status' => $isTerminalFailure ? PayoutStatus::Failed : PayoutStatus::Retrying,
                ])->save();

                if ($isTerminalFailure) {
                    $this->releaseFailedPayoutBalance->handle(
                        payout: $lockedPayout,
                        immutableReference: 'payout-'.$lockedPayout->id.'-failed-release',
                        description: 'Released failed payout debit',
                        metadata: [
                            'attempt_number' => $attemptNumber,
                            'failure_reason' => $failureReason,
                        ],
                    );
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

        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        if ($updatedPayout->status === PayoutStatus::Failed) {
            $this->createPayoutExceptionCase->handle(
                payout: $updatedPayout,
                subject: 'Payout transfer failed',
                description: $updatedPayout->failure_reason,
                priority: SupportCasePriority::High,
                metadata: ['source' => 'manual_processing'],
            );
        }

        return $updatedPayout->refresh();
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

    private function retryDelayMinutes(): int
    {
        $retryDelay = config('lartisan.payouts.retry_delay_minutes', 60);

        return max(1, is_numeric($retryDelay) ? (int) $retryDelay : 60);
    }
}
