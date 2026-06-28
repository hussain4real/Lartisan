<?php

namespace App\Models;

use App\Enums\OperationalHealthStatus;
use Database\Factories\SystemHealthSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $generated_by
 * @property OperationalHealthStatus $status
 * @property array<string, mixed> $summary
 * @property array<string, array<string, mixed>> $checks
 * @property int $queue_failed_jobs_count
 * @property int $queue_pending_jobs_count
 * @property Carbon|null $oldest_failed_job_at
 * @property Carbon|null $scheduler_last_seen_at
 * @property Carbon|null $backup_last_successful_at
 * @property Carbon $generated_at
 */
#[Fillable([
    'generated_by',
    'status',
    'summary',
    'checks',
    'queue_failed_jobs_count',
    'queue_pending_jobs_count',
    'oldest_failed_job_at',
    'scheduler_last_seen_at',
    'backup_last_successful_at',
    'generated_at',
])]
class SystemHealthSnapshot extends Model
{
    /** @use HasFactory<SystemHealthSnapshotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'backup_last_successful_at' => 'datetime',
            'checks' => 'array',
            'generated_at' => 'datetime',
            'oldest_failed_job_at' => 'datetime',
            'queue_failed_jobs_count' => 'integer',
            'queue_pending_jobs_count' => 'integer',
            'scheduler_last_seen_at' => 'datetime',
            'status' => OperationalHealthStatus::class,
            'summary' => 'array',
        ];
    }
}
