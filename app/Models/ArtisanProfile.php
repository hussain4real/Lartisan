<?php

namespace App\Models;

use App\Enums\AdminProfileStatus;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Database\Factories\ArtisanProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $business_name
 * @property ArtisanVerificationStatus $verification_status
 * @property ArtisanSubscriptionStatus $subscription_status
 * @property ArtisanAvailabilityStatus $availability_status
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $local_government_id
 * @property int|null $territory_id
 * @property int|null $onboarded_by_agent_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property bool $is_public
 */
#[Fillable([
    'team_id',
    'user_id',
    'business_name',
    'verification_status',
    'subscription_status',
    'availability_status',
    'country_id',
    'state_id',
    'local_government_id',
    'territory_id',
    'onboarded_by_agent_id',
    'approved_by',
    'approved_at',
    'is_public',
    'internal_notes',
])]
class ArtisanProfile extends Model
{
    /** @use HasFactory<ArtisanProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsTo<LocalGovernment, $this>
     */
    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
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
    public function onboardedByAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onboarded_by_agent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    public function scopeInState(Builder $query, State|int $state): void
    {
        $query->where('state_id', $state instanceof State ? $state->id : $state);
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    public function scopeInLocalGovernment(Builder $query, LocalGovernment|int $localGovernment): void
    {
        $query->where('local_government_id', $localGovernment instanceof LocalGovernment ? $localGovernment->id : $localGovernment);
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    public function scopeInTerritory(Builder $query, Territory|int $territory): void
    {
        $query->where('territory_id', $territory instanceof Territory ? $territory->id : $territory);
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $adminProfile = $user->adminProfile()->first();

        $query->where(function (Builder $query) use ($adminProfile, $user): void {
            $query->ownedBy($user);

            if (! $adminProfile instanceof AdminProfile || $adminProfile->status !== AdminProfileStatus::Active) {
                return;
            }

            if ($adminProfile->role === PlatformRole::StateCoordinator) {
                $this->scopeStateVisibility($query, $adminProfile);

                return;
            }

            if ($adminProfile->role === PlatformRole::LocalGovernmentAdmin) {
                $this->scopeLocalGovernmentVisibility($query, $adminProfile);

                return;
            }

            if ($adminProfile->role === PlatformRole::AreaAgent) {
                $this->scopeAreaAgentVisibility($query, $user);
            }
        });
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    private function scopeStateVisibility(Builder $query, AdminProfile $adminProfile): void
    {
        if ($adminProfile->scope_type === (new State)->getMorphClass() && $adminProfile->scope_id !== null) {
            $query->orWhere('state_id', $adminProfile->scope_id);
        }
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    private function scopeLocalGovernmentVisibility(Builder $query, AdminProfile $adminProfile): void
    {
        if ($adminProfile->scope_type === (new LocalGovernment)->getMorphClass() && $adminProfile->scope_id !== null) {
            $query->orWhere('local_government_id', $adminProfile->scope_id);
        }
    }

    /**
     * @param  Builder<ArtisanProfile>  $query
     */
    private function scopeAreaAgentVisibility(Builder $query, User $user): void
    {
        $query
            ->orWhere('onboarded_by_agent_id', $user->id)
            ->orWhereIn(
                'territory_id',
                $user->areaAgentAssignments()
                    ->whereNull('ends_at')
                    ->select('territory_id'),
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'availability_status' => ArtisanAvailabilityStatus::class,
            'is_public' => 'boolean',
            'subscription_status' => ArtisanSubscriptionStatus::class,
            'verification_status' => ArtisanVerificationStatus::class,
        ];
    }
}
