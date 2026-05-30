<?php

namespace App\Models;

use App\Enums\TerritoryType;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $local_government_id
 * @property TerritoryType $type
 * @property string $name
 * @property string $slug
 * @property array<string, mixed>|null $boundaries
 * @property bool $active
 */
#[Fillable(['local_government_id', 'type', 'name', 'slug', 'boundaries', 'active'])]
class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LocalGovernment, $this>
     */
    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
    }

    /**
     * @return HasMany<AreaAgentAssignment, $this>
     */
    public function areaAgentAssignments(): HasMany
    {
        return $this->hasMany(AreaAgentAssignment::class);
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'boundaries' => 'array',
            'type' => TerritoryType::class,
        ];
    }
}
