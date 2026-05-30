<?php

namespace App\Models;

use Database\Factories\AreaAgentAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $territory_id
 * @property int|null $assigned_by
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
#[Fillable(['user_id', 'territory_id', 'starts_at', 'ends_at', 'assigned_by', 'reason'])]
class AreaAgentAssignment extends Model
{
    /** @use HasFactory<AreaAgentAssignmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'starts_at' => 'datetime',
        ];
    }
}
