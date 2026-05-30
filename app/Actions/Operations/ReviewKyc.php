<?php

namespace App\Actions\Operations;

use App\Actions\Artisans\RecordStatusHistory;
use App\Actions\Audit\RecordAuditLog;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\KycRiskLevel;
use App\Enums\PlatformPermission;
use App\Enums\ReasonCodeCategory;
use App\Models\KycSubmission;
use App\Models\ReasonCode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ReviewKyc
{
    public function __construct(
        private readonly RecordStatusHistory $recordStatusHistory,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(
        KycSubmission $submission,
        User $reviewer,
        ReasonCode $reasonCode,
        ?string $notes = null,
        ?KycRiskLevel $riskLevel = null,
    ): KycSubmission {
        return $this->transition(
            submission: $submission,
            reviewer: $reviewer,
            targetStatus: ArtisanVerificationStatus::LgaReview,
            reasonCode: $reasonCode,
            notes: $notes,
            riskLevel: $riskLevel,
        );
    }

    public function transition(
        KycSubmission $submission,
        User $reviewer,
        ArtisanVerificationStatus $targetStatus,
        ReasonCode $reasonCode,
        ?string $notes = null,
        ?KycRiskLevel $riskLevel = null,
    ): KycSubmission {
        $this->authorizeTransition($submission, $reviewer, $targetStatus);
        $this->ensureReasonCodeCategory($reasonCode);
        $this->ensureAllowedTransition($submission->status, $targetStatus);

        return DB::transaction(function () use ($submission, $reviewer, $targetStatus, $reasonCode, $notes, $riskLevel): KycSubmission {
            $submission->refresh();
            $profile = $submission->artisanProfile()->firstOrFail();
            $previousSubmissionStatus = $submission->status;
            $previousProfileStatus = $profile->verification_status;
            $trimmedNotes = $this->blankToNull($notes);

            $submission->forceFill([
                'status' => $targetStatus,
                'risk_level' => $riskLevel ?? $submission->risk_level,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'decision_reason' => $trimmedNotes,
                'reason_code_id' => $reasonCode->id,
            ])->save();

            $profileUpdates = [
                'verification_status' => $targetStatus,
            ];

            if ($targetStatus === ArtisanVerificationStatus::Approved) {
                $profileUpdates['approved_by'] = $reviewer->id;
                $profileUpdates['approved_at'] = now();
                $profileUpdates['is_public'] = true;
            }

            if ($targetStatus !== ArtisanVerificationStatus::Approved) {
                $profileUpdates['approved_by'] = null;
                $profileUpdates['approved_at'] = null;
            }

            $profile->forceFill($profileUpdates)->save();

            $this->recordStatusHistory->handle(
                statusable: $submission,
                actor: $reviewer,
                fromStatus: $previousSubmissionStatus->value,
                toStatus: $targetStatus->value,
                reason: $reasonCode->code,
                metadata: ['notes' => $trimmedNotes],
            );

            $this->recordStatusHistory->handle(
                statusable: $profile,
                actor: $reviewer,
                fromStatus: $previousProfileStatus->value,
                toStatus: $targetStatus->value,
                reason: $reasonCode->code,
                metadata: ['kyc_submission_id' => $submission->id],
            );

            $this->recordAuditLog->handle(
                actor: $reviewer,
                action: 'kyc.'.$targetStatus->value,
                subject: $submission,
                before: [
                    'submission_status' => $previousSubmissionStatus->value,
                    'profile_status' => $previousProfileStatus->value,
                ],
                after: [
                    'submission_status' => $targetStatus->value,
                    'profile_status' => $targetStatus->value,
                    'reviewed_by' => $reviewer->id,
                ],
                reason: $trimmedNotes,
                reasonCode: $reasonCode,
            );

            return $submission->refresh();
        });
    }

    private function authorizeTransition(
        KycSubmission $submission,
        User $reviewer,
        ArtisanVerificationStatus $targetStatus,
    ): void {
        Gate::forUser($reviewer)->authorize('update', $submission);

        $requiresEscalatedReview = $submission->status === ArtisanVerificationStatus::Escalated
            || ($targetStatus === ArtisanVerificationStatus::Approved && $submission->risk_level === KycRiskLevel::High);

        if ($targetStatus === ArtisanVerificationStatus::Escalated) {
            $allowed = $reviewer->can(PlatformPermission::ReviewStandardKyc->value)
                || $reviewer->can(PlatformPermission::ReviewEscalatedKyc->value);
        } elseif ($requiresEscalatedReview) {
            $allowed = $reviewer->can(PlatformPermission::ReviewEscalatedKyc->value);
        } else {
            $allowed = $reviewer->can(PlatformPermission::ReviewStandardKyc->value)
                || $reviewer->can(PlatformPermission::ReviewEscalatedKyc->value);
        }

        throw_unless($allowed, AuthorizationException::class, 'This user cannot review this KYC submission.');
    }

    private function ensureReasonCodeCategory(ReasonCode $reasonCode): void
    {
        throw_if(
            $reasonCode->category !== ReasonCodeCategory::KycDecision,
            InvalidArgumentException::class,
            'The reason code must be a KYC decision reason.',
        );
    }

    private function ensureAllowedTransition(
        ArtisanVerificationStatus $currentStatus,
        ArtisanVerificationStatus $targetStatus,
    ): void {
        $allowedTargets = match ($currentStatus) {
            ArtisanVerificationStatus::Draft => [
                ArtisanVerificationStatus::LgaReview,
                ArtisanVerificationStatus::Returned,
            ],
            ArtisanVerificationStatus::Submitted,
            ArtisanVerificationStatus::FieldCheckPending,
            ArtisanVerificationStatus::FieldCheckComplete,
            ArtisanVerificationStatus::LgaReview => [
                ArtisanVerificationStatus::LgaReview,
                ArtisanVerificationStatus::Approved,
                ArtisanVerificationStatus::Returned,
                ArtisanVerificationStatus::Rejected,
                ArtisanVerificationStatus::Escalated,
            ],
            ArtisanVerificationStatus::Escalated => [
                ArtisanVerificationStatus::Approved,
                ArtisanVerificationStatus::Returned,
                ArtisanVerificationStatus::Rejected,
            ],
            ArtisanVerificationStatus::Approved,
            ArtisanVerificationStatus::Returned,
            ArtisanVerificationStatus::Rejected,
            ArtisanVerificationStatus::Suspended => [],
        };

        throw_unless(
            in_array($targetStatus, $allowedTargets, true),
            InvalidArgumentException::class,
            sprintf(
                'Cannot transition KYC from %s to %s.',
                $currentStatus->value,
                $targetStatus->value,
            ),
        );
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
