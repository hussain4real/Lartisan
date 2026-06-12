<?php

namespace App\Models;

use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use Database\Factories\SupportCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $requester_id
 * @property int|null $owner_id
 * @property string|null $supportable_type
 * @property int|null $supportable_id
 * @property SupportCaseCategory $category
 * @property SupportCasePriority $priority
 * @property SupportCaseStatus $status
 * @property string $subject
 * @property string|null $description
 * @property string|null $resolution_notes
 * @property Carbon $opened_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'requester_id',
    'owner_id',
    'supportable_type',
    'supportable_id',
    'category',
    'priority',
    'status',
    'subject',
    'description',
    'resolution_notes',
    'opened_at',
    'resolved_at',
    'closed_at',
    'metadata',
])]
class SupportCase extends Model
{
    /** @use HasFactory<SupportCaseFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function supportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SupportCaseCategory::class,
            'closed_at' => 'datetime',
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'priority' => SupportCasePriority::class,
            'resolved_at' => 'datetime',
            'status' => SupportCaseStatus::class,
        ];
    }
}
