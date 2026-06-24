<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Database\Factories\LocalGovernmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $state_id
 * @property string $name
 * @property string $slug
 * @property bool $active
 */
#[Fillable(['state_id', 'name', 'slug', 'active'])]
class LocalGovernment extends Model
{
    /** @use HasFactory<LocalGovernmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return HasMany<Territory, $this>
     */
    public function territories(): HasMany
    {
        return $this->hasMany(Territory::class);
    }

    /**
     * @return HasMany<ArtisanProfile, $this>
     */
    public function artisanProfiles(): HasMany
    {
        return $this->hasMany(ArtisanProfile::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @param  Builder<LocalGovernment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can(PlatformPermission::ManageTerritories->value)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $adminProfile = $user->adminProfile()->active()->first();

        if (! $adminProfile instanceof AdminProfile) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($adminProfile->role === PlatformRole::StateCoordinator
            && $adminProfile->scope_type === (new State)->getMorphClass()
            && $adminProfile->scope_id !== null) {
            $query->where('state_id', $adminProfile->scope_id);

            return;
        }

        if ($adminProfile->role === PlatformRole::LocalGovernmentAdmin
            && $adminProfile->scope_type === (new LocalGovernment)->getMorphClass()
            && $adminProfile->scope_id !== null) {
            $query->whereKey($adminProfile->scope_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
