<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int $artisan_profile_id
 * @property int|null $artisan_service_id
 * @property int|null $service_category_id
 * @property BookingStatus $status
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property Carbon|null $scheduled_at
 * @property string|null $description
 * @property int|null $quoted_amount
 * @property string $currency_code
 * @property array<string, mixed> $address_snapshot
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $local_government_id
 * @property int|null $territory_id
 * @property string $tracker_code
 * @property string $secure_token_hash
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $wallet_released_at
 */
#[Fillable([
    'customer_id',
    'artisan_profile_id',
    'artisan_service_id',
    'service_category_id',
    'status',
    'customer_name',
    'customer_phone',
    'customer_email',
    'scheduled_at',
    'description',
    'quoted_amount',
    'currency_code',
    'address_snapshot',
    'country_id',
    'state_id',
    'local_government_id',
    'territory_id',
    'tracker_code',
    'secure_token_hash',
    'accepted_at',
    'rejected_at',
    'started_at',
    'finished_at',
    'confirmed_at',
    'wallet_released_at',
])]
class Booking extends Model implements HasMedia
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, InteractsWithMedia;

    public const MEDIA_COLLECTION = 'booking_attachments';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk('local')
            ->acceptsFile(fn (File $file): bool => in_array($file->mimeType, [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return BelongsTo<ArtisanService, $this>
     */
    public function artisanService(): BelongsTo
    {
        return $this->belongsTo(ArtisanService::class);
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
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
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'address_snapshot' => 'array',
            'confirmed_at' => 'datetime',
            'finished_at' => 'datetime',
            'quoted_amount' => 'integer',
            'rejected_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'status' => BookingStatus::class,
            'wallet_released_at' => 'datetime',
        ];
    }
}
