<?php

namespace App\Actions\Operations;

use App\Actions\Artisans\RecordStatusHistory;
use App\Actions\Audit\RecordAuditLog;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\ReasonCodeCategory;
use App\Models\ArtisanProfile;
use App\Models\ReasonCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SuspendArtisanProfile
{
    public function __construct(
        private readonly RecordStatusHistory $recordStatusHistory,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(
        ArtisanProfile $profile,
        User $actor,
        ReasonCode $reasonCode,
        ?string $reason = null,
    ): ArtisanProfile {
        Gate::forUser($actor)->authorize('update', $profile);
        $this->ensureReasonCodeCategory($reasonCode);

        return DB::transaction(function () use ($profile, $actor, $reasonCode, $reason): ArtisanProfile {
            $profile->refresh();
            $previousStatus = $profile->verification_status;
            $previousPublicListing = $profile->is_public;
            $trimmedReason = $this->blankToNull($reason);

            $profile->forceFill([
                'verification_status' => ArtisanVerificationStatus::Suspended,
                'is_public' => false,
                'suspension_reason_code_id' => $reasonCode->id,
                'suspended_by' => $actor->id,
                'suspended_at' => now(),
            ])->save();

            $this->recordStatusHistory->handle(
                statusable: $profile,
                actor: $actor,
                fromStatus: $previousStatus->value,
                toStatus: ArtisanVerificationStatus::Suspended->value,
                reason: $reasonCode->code,
                metadata: ['reason' => $trimmedReason],
            );

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'artisan_profile.suspended',
                subject: $profile,
                before: [
                    'verification_status' => $previousStatus->value,
                    'is_public' => $previousPublicListing,
                ],
                after: [
                    'verification_status' => ArtisanVerificationStatus::Suspended->value,
                    'is_public' => false,
                    'suspended_by' => $actor->id,
                ],
                reason: $trimmedReason,
                reasonCode: $reasonCode,
            );

            return $profile->refresh();
        });
    }

    private function ensureReasonCodeCategory(ReasonCode $reasonCode): void
    {
        throw_if(
            $reasonCode->category !== ReasonCodeCategory::Suspension,
            InvalidArgumentException::class,
            'The reason code must be a suspension reason.',
        );
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
