<?php

namespace App\Models;

use App\Enums\ArtisanServiceStatus;
use Database\Factories\ArtisanServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property int $service_category_id
 * @property string $title
 * @property string|null $description
 * @property string|null $starting_price
 * @property string $currency_code
 * @property ArtisanServiceStatus $status
 * @property int $sort_order
 */
#[Fillable([
    'artisan_profile_id',
    'service_category_id',
    'title',
    'description',
    'starting_price',
    'currency_code',
    'status',
    'sort_order',
])]
class ArtisanService extends Model
{
    /** @use HasFactory<ArtisanServiceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
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
            'sort_order' => 'integer',
            'starting_price' => 'decimal:2',
            'status' => ArtisanServiceStatus::class,
        ];
    }
}
