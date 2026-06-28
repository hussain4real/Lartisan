<?php

namespace App\Models;

use App\Enums\RestoreTestStatus;
use Database\Factories\RestoreTestRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $verified_by
 * @property RestoreTestStatus $status
 * @property string|null $backup_disk
 * @property string|null $backup_path
 * @property bool $database_verified
 * @property bool $media_verified
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon $tested_at
 */
#[Fillable([
    'verified_by',
    'status',
    'backup_disk',
    'backup_path',
    'database_verified',
    'media_verified',
    'notes',
    'metadata',
    'tested_at',
])]
class RestoreTestRecord extends Model
{
    /** @use HasFactory<RestoreTestRecordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'database_verified' => 'boolean',
            'media_verified' => 'boolean',
            'metadata' => 'array',
            'status' => RestoreTestStatus::class,
            'tested_at' => 'datetime',
        ];
    }
}
