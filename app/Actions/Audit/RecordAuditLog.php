<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\ReasonCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditLog
{
    /**
     * Record an append-only audit entry for a domain action.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(
        ?User $actor,
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?ReasonCode $reasonCode = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'reason_code_id' => $reasonCode?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
