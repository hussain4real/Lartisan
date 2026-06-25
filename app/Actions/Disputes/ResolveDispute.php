<?php

namespace App\Actions\Disputes;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\DisputeStatus;
use App\Enums\PlatformPermission;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseStatus;
use App\Models\ArtisanProfile;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResolveDispute
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Dispute $dispute, User $actor, string $resolution, bool $hideReview = false): Dispute
    {
        if (trim($resolution) === '') {
            throw new InvalidArgumentException('A dispute resolution is required.');
        }

        $updatedDispute = DB::transaction(function () use ($dispute, $actor, $resolution, $hideReview): Dispute {
            $lockedDispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $this->authorize($lockedDispute, $actor);

            if (in_array($lockedDispute->status, [DisputeStatus::Resolved, DisputeStatus::Closed], true)) {
                throw new InvalidArgumentException('This dispute is already resolved.');
            }

            $before = ['status' => $lockedDispute->status->value];
            $lockedDispute->forceFill([
                'resolved_by_id' => $actor->id,
                'resolved_at' => now(),
                'resolution' => $resolution,
                'status' => DisputeStatus::Resolved,
            ])->save();

            $lockedDispute->supportCases()->update([
                'resolution_notes' => $resolution,
                'resolved_at' => now(),
                'status' => SupportCaseStatus::Resolved->value,
            ]);

            if ($hideReview) {
                $review = $lockedDispute->review()->first();
                $review?->forceFill([
                    'moderated_by' => $actor->id,
                    'moderated_at' => now(),
                    'moderation_notes' => $resolution,
                    'status' => ReviewStatus::Hidden,
                ])->save();
            }

            $this->recordAuditLog->handle(
                actor: $actor,
                action: 'dispute.resolved',
                subject: $lockedDispute,
                before: $before,
                after: ['status' => $lockedDispute->status->value],
                reason: $resolution,
            );

            return $lockedDispute->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->disputeResolved($updatedDispute);

        foreach ($updatedDispute->supportCases()->get() as $supportCase) {
            $this->sendLifecycleNotification->supportCaseResolved($supportCase);
        }

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

        throw new AuthorizationException('You cannot resolve this dispute.');
    }
}
