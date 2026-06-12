<?php

namespace App\Models;

use App\Enums\ReportSnapshotScope;
use Database\Factories\ReportSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $generated_by
 * @property ReportSnapshotScope $scope
 * @property string|null $scopeable_type
 * @property int|null $scopeable_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property array<string, mixed> $metrics
 * @property Carbon $generated_at
 */
#[Fillable([
    'generated_by',
    'scope',
    'scopeable_type',
    'scopeable_id',
    'period_start',
    'period_end',
    'metrics',
    'generated_at',
])]
class ReportSnapshot extends Model
{
    /** @use HasFactory<ReportSnapshotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'metrics' => 'array',
            'period_end' => 'date',
            'period_start' => 'date',
            'scope' => ReportSnapshotScope::class,
        ];
    }
}
