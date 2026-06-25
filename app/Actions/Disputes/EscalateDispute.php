<?php

namespace App\Actions\Disputes;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\PlatformPermission;
use App\Models\ArtisanProfile;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EscalateDispute
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Dispute $dispute, User $actor, string $reason): Dispute
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('An escalation reason is required.');
        }

        $updatedDispute = DB::transaction(function () use ($dispute, $actor, $reason): Dispute {
            $lockedDispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $this->authorize($lockedDispute, $actor);

            if (! in_array($lockedDispute->status, [DisputeStatus::Open, DisputeStatus::UnderLocalGovernmentReview], true)) {
                throw new InvalidArgumentException('Only open disputes can be escalated.');
            }

            $before = ['status' => $lockedDispute->status->value];
            $lockedDispute->forceFill([
                'escalated_at' => now(),
                'escalation_reason' => $reason,
                'severity' => $lockedDispute->severity === DisputeSeverity::Critical
                    ? DisputeSeverity::Critical
                    : DisputeSeverity::High,
                'status' => DisputeStatus::EscalatedToState,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'dispute.escalated',
                subject: $lockedDispute,
                before: $before,
                after: ['status' => $lockedDispute->status->value],
                reason: $reason,
            );

            return $lockedDispute->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->disputeEscalated($updatedDispute);

        return $updatedDispute->refresh();
    }

    private function authorize(Dispute $dispute, User $actor): void
    {
        $profile = $dispute->artisanProfile()->first();

        if ($profile instanceof ArtisanProfile
            && $actor->can(PlatformPermission::ManageSupportCases->value)
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($actor)->exists()) {
            return;
        }

        throw new AuthorizationException('You cannot escalate this dispute.');
    }
}
