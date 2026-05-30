<?php

namespace App\Models;

use App\Enums\AdminProfileStatus;
use App\Enums\PlatformRole;
use Database\Factories\AdminProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property PlatformRole $role
 * @property AdminProfileStatus $status
 * @property int|null $appointed_by
 */
#[Fillable(['user_id', 'role', 'scope_type', 'scope_id', 'status', 'appointed_by', 'appointed_at'])]
class AdminProfile extends Model
{
    /** @use HasFactory<AdminProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appointedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointed_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointed_at' => 'datetime',
            'role' => PlatformRole::class,
            'status' => AdminProfileStatus::class,
        ];
    }
}
