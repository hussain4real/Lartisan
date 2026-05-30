<?php

namespace App\Models;

use App\Enums\AdminProfileStatus;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Database\Factories\AdminProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property PlatformRole $role
 * @property AdminProfileStatus $status
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property int|null $appointed_by
 * @property Carbon|null $appointed_at
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
     * @param  Builder<AdminProfile>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AdminProfileStatus::Active);
    }

    /**
     * @param  Builder<AdminProfile>  $query
     */
    public function scopeForRole(Builder $query, PlatformRole $role): void
    {
        $query->where('role', $role);
    }

    /**
     * @param  Builder<AdminProfile>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $viewerProfile = $user->adminProfile()->active()->first();

        $query->where(function (Builder $query) use ($user, $viewerProfile): void {
            $query->where('user_id', $user->id);

            if (! $viewerProfile instanceof AdminProfile) {
                return;
            }

            if ($viewerProfile->role === PlatformRole::StateCoordinator) {
                $this->scopeStateAdminVisibility($query, $viewerProfile);

                return;
            }

            if ($viewerProfile->role === PlatformRole::LocalGovernmentAdmin) {
                $this->scopeLocalGovernmentAdminVisibility($query, $viewerProfile);
            }
        });
    }

    /**
     * @param  Builder<AdminProfile>  $query
     */
    private function scopeStateAdminVisibility(Builder $query, AdminProfile $viewerProfile): void
    {
        if ($viewerProfile->scope_type !== (new State)->getMorphClass() || $viewerProfile->scope_id === null) {
            return;
        }

        $localGovernmentIds = LocalGovernment::query()
            ->where('state_id', $viewerProfile->scope_id)
            ->select('id');

        $query
            ->orWhere(function (Builder $query) use ($viewerProfile): void {
                $query
                    ->where('scope_type', (new State)->getMorphClass())
                    ->where('scope_id', $viewerProfile->scope_id);
            })
            ->orWhere(function (Builder $query) use ($localGovernmentIds): void {
                $query
                    ->where('scope_type', (new LocalGovernment)->getMorphClass())
                    ->whereIn('scope_id', $localGovernmentIds);
            });
    }

    /**
     * @param  Builder<AdminProfile>  $query
     */
    private function scopeLocalGovernmentAdminVisibility(Builder $query, AdminProfile $viewerProfile): void
    {
        if ($viewerProfile->scope_type !== (new LocalGovernment)->getMorphClass() || $viewerProfile->scope_id === null) {
            return;
        }

        $query
            ->orWhere(function (Builder $query) use ($viewerProfile): void {
                $query
                    ->where('scope_type', (new LocalGovernment)->getMorphClass())
                    ->where('scope_id', $viewerProfile->scope_id);
            })
            ->orWhere(function (Builder $query) use ($viewerProfile): void {
                $query
                    ->where('scope_type', (new Territory)->getMorphClass())
                    ->whereIn(
                        'scope_id',
                        Territory::query()
                            ->where('local_government_id', $viewerProfile->scope_id)
                            ->select('id'),
                    );
            });
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
