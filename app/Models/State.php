<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Database\Factories\StateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $country_id
 * @property string $name
 * @property string $slug
 * @property bool $active
 */
#[Fillable(['country_id', 'name', 'slug', 'active'])]
class State extends Model
{
    /** @use HasFactory<StateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<LocalGovernment, $this>
     */
    public function localGovernments(): HasMany
    {
        return $this->hasMany(LocalGovernment::class);
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
     * @param  Builder<State>  $query
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
            $query->whereKey($adminProfile->scope_id);

            return;
        }

        if ($adminProfile->role === PlatformRole::LocalGovernmentAdmin
            && $adminProfile->scope_type === (new LocalGovernment)->getMorphClass()
            && $adminProfile->scope_id !== null) {
            $query->whereIn(
                'id',
                LocalGovernment::query()
                    ->whereKey($adminProfile->scope_id)
                    ->select('state_id'),
            );

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
