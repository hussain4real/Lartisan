<?php

namespace App\Actions\SupportCases;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PlatformPermission;
use App\Enums\SupportCaseStatus;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AssignSupportCase
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(SupportCase $supportCase, User $actor, User $owner): SupportCase
    {
        Gate::forUser($actor)->authorize('update', $supportCase);

        if (! $owner->can(PlatformPermission::ManageSupportCases->value)) {
            throw new InvalidArgumentException('Support cases can only be assigned to support operators.');
        }

        $before = [
            'owner_id' => $supportCase->owner_id,
            'status' => $supportCase->status->value,
        ];

        $supportCase->forceFill([
            'owner_id' => $owner->id,
            'status' => $supportCase->status === SupportCaseStatus::Open
                ? SupportCaseStatus::InProgress
                : $supportCase->status,
        ])->save();

        $this->recordAuditLog->handle(
            actor: $actor,
            action: 'support_case.assigned',
            subject: $supportCase,
            before: $before,
            after: [
                'owner_id' => $supportCase->owner_id,
                'status' => $supportCase->status->value,
            ],
        );

        return $supportCase->refresh();
    }
}
