<?php

namespace App\Actions\Operations;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PlatformRole;
use App\Enums\ReasonCodeCategory;
use App\Models\AreaAgentAssignment;
use App\Models\ReasonCode;
use App\Models\Territory;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AssignTerritory
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(
        User $areaAgent,
        Territory $territory,
        User $actor,
        ?ReasonCode $reasonCode = null,
        ?string $reason = null,
        ?DateTimeInterface $startsAt = null,
    ): AreaAgentAssignment {
        Gate::forUser($actor)->authorize('assign', [AreaAgentAssignment::class, $territory]);

        throw_unless(
            $areaAgent->hasRole(PlatformRole::AreaAgent->value),
            InvalidArgumentException::class,
            'Territories can only be assigned to area agents.',
        );

        $this->ensureReasonCodeCategory($reasonCode);

        $effectiveStartsAt = $startsAt instanceof DateTimeInterface
            ? Carbon::instance($startsAt)
            : now();

        return DB::transaction(function () use ($actor, $areaAgent, $territory, $reasonCode, $reason, $effectiveStartsAt): AreaAgentAssignment {
            $previousAssignments = $areaAgent->areaAgentAssignments()
                ->active()
                ->with('territory:id,name')
                ->get();

            $areaAgent->areaAgentAssignments()
                ->active()
                ->update(['ends_at' => $effectiveStartsAt]);

            $assignment = AreaAgentAssignment::query()->create([
                'user_id' => $areaAgent->id,
                'territory_id' => $territory->id,
                'starts_at' => $effectiveStartsAt,
                'ends_at' => null,
                'assigned_by' => $actor->id,
                'reason' => $this->blankToNull($reason),
                'reason_code_id' => $reasonCode?->id,
            ]);

            $this->recordAuditLog->handle(
                actor: $actor,
                action: $previousAssignments->isEmpty() ? 'territory.assigned' : 'territory.reassigned',
                subject: $assignment,
                before: [
                    'assignments' => $previousAssignments
                        ->map(fn (AreaAgentAssignment $assignment): array => [
                            'id' => $assignment->id,
                            'territory_id' => $assignment->territory_id,
                            'territory_name' => $assignment->territory?->name,
                        ])
                        ->values()
                        ->all(),
                ],
                after: [
                    'assignment_id' => $assignment->id,
                    'area_agent_id' => $areaAgent->id,
                    'territory_id' => $territory->id,
                ],
                reason: $this->blankToNull($reason),
                reasonCode: $reasonCode,
            );

            return $assignment->refresh();
        });
    }

    private function ensureReasonCodeCategory(?ReasonCode $reasonCode): void
    {
        throw_if(
            $reasonCode instanceof ReasonCode && $reasonCode->category !== ReasonCodeCategory::TerritoryAssignment,
            InvalidArgumentException::class,
            'The reason code must be a territory assignment reason.',
        );
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
