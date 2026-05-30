<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Database\Factories\AreaAgentAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $reason_code_id
 */
#[Fillable(['user_id', 'territory_id', 'starts_at', 'ends_at', 'assigned_by', 'reason', 'reason_code_id'])]
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
     * @return BelongsTo<ReasonCode, $this>
     */
    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    /**
     * @param  Builder<AreaAgentAssignment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('ends_at');
    }

    /**
     * @param  Builder<AreaAgentAssignment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $adminProfile = $user->adminProfile()->active()->first();

        $query->where(function (Builder $query) use ($adminProfile, $user): void {
            $query->where('user_id', $user->id);

            if (! $adminProfile instanceof AdminProfile) {
                return;
            }

            if ($adminProfile->role === PlatformRole::StateCoordinator
                && $adminProfile->scope_type === (new State)->getMorphClass()
                && $adminProfile->scope_id !== null) {
                $query->orWhereHas('territory.localGovernment', function (Builder $query) use ($adminProfile): void {
                    $query->where('state_id', $adminProfile->scope_id);
                });

                return;
            }

            if ($adminProfile->role === PlatformRole::LocalGovernmentAdmin
                && $adminProfile->scope_type === (new LocalGovernment)->getMorphClass()
                && $adminProfile->scope_id !== null) {
                $query->orWhereHas('territory', function (Builder $query) use ($adminProfile): void {
                    $query->where('local_government_id', $adminProfile->scope_id);
                });
            }
        });
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
