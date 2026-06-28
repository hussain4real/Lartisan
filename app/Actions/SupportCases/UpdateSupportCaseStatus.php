<?php

namespace App\Actions\SupportCases;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\SupportCaseStatus;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateSupportCaseStatus
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(
        SupportCase $supportCase,
        User $actor,
        SupportCaseStatus $status,
        ?string $resolutionNotes = null,
    ): SupportCase {
        Gate::forUser($actor)->authorize('update', $supportCase);

        $resolutionNotes = $resolutionNotes === null ? null : trim($resolutionNotes);

        if (in_array($status, [SupportCaseStatus::Resolved, SupportCaseStatus::Closed], true)
            && ($resolutionNotes === null || $resolutionNotes === '')) {
            throw new InvalidArgumentException('Resolution notes are required before resolving or closing a support case.');
        }

        $before = [
            'status' => $supportCase->status->value,
            'owner_id' => $supportCase->owner_id,
        ];

        $updates = [
            'status' => $status,
        ];

        if ($status === SupportCaseStatus::Open || $status === SupportCaseStatus::InProgress) {
            $updates['resolved_at'] = null;
            $updates['closed_at'] = null;
        }

        if ($status === SupportCaseStatus::Resolved) {
            $updates['resolved_at'] = now();
            $updates['closed_at'] = null;
            $updates['resolution_notes'] = $resolutionNotes;
        }

        if ($status === SupportCaseStatus::Closed) {
            $updates['resolved_at'] = $supportCase->resolved_at ?? now();
            $updates['closed_at'] = now();
            $updates['resolution_notes'] = $resolutionNotes;
        }

        $supportCase->forceFill($updates)->save();

        $this->recordAuditLog->handle(
            actor: $actor,
            action: 'support_case.status_updated',
            subject: $supportCase,
            before: $before,
            after: [
                'status' => $supportCase->status->value,
                'owner_id' => $supportCase->owner_id,
            ],
            reason: $resolutionNotes,
        );

        if ($status === SupportCaseStatus::Resolved) {
            $this->sendLifecycleNotification->supportCaseResolved($supportCase);
        }

        return $supportCase->refresh();
    }
}
