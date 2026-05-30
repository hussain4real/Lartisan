<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\StatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $statusable_type
 * @property int $statusable_id
 * @property int|null $actor_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 */
#[Fillable([
    'statusable_type',
    'statusable_id',
    'actor_id',
    'from_status',
    'to_status',
    'reason',
    'metadata',
])]
class StatusHistory extends Model
{
    /** @use HasFactory<StatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function statusable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
