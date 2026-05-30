<?php

namespace App\Actions\Artisans;

use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class RecordStatusHistory
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Model $statusable,
        ?User $actor,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?array $metadata = null,
    ): StatusHistory {
        $toStatus = trim($toStatus);

        if ($toStatus === '') {
            throw new InvalidArgumentException('A status history target status is required.');
        }

        return StatusHistory::query()->create([
            'statusable_type' => $statusable->getMorphClass(),
            'statusable_id' => $statusable->getKey(),
            'actor_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
