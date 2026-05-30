<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property int|null $reason_code_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason', 'reason_code_id', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ReasonCode, $this>
     */
    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $artisanProfileType = (new ArtisanProfile)->getMorphClass();
        $customerProfileType = (new CustomerProfile)->getMorphClass();

        $query->where(function (Builder $query) use ($artisanProfileType, $customerProfileType, $user): void {
            $query
                ->where('actor_id', $user->id)
                ->orWhere(function (Builder $query) use ($artisanProfileType, $user): void {
                    $query
                        ->where('subject_type', $artisanProfileType)
                        ->whereIn('subject_id', ArtisanProfile::query()->visibleTo($user)->select('id'));
                })
                ->orWhere(function (Builder $query) use ($customerProfileType, $user): void {
                    $query
                        ->where('subject_type', $customerProfileType)
                        ->whereIn('subject_id', CustomerProfile::query()->visibleTo($user)->select('id'));
                });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'after' => 'array',
            'before' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Bootstrap the model and prevent mutation of written audit records.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }
}
