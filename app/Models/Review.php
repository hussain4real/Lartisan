<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Support\MediaDisk;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $customer_id
 * @property int $artisan_profile_id
 * @property int $rating
 * @property string|null $comment
 * @property ReviewStatus $status
 * @property Carbon $reviewed_at
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property string|null $moderation_notes
 * @property int|null $artisan_response_by
 * @property string|null $artisan_response
 * @property Carbon|null $artisan_responded_at
 * @property string|null $moderation_signal
 * @property int $moderation_score
 * @property array<string, mixed>|null $moderation_metadata
 */
#[Fillable([
    'booking_id',
    'customer_id',
    'artisan_profile_id',
    'rating',
    'comment',
    'status',
    'reviewed_at',
    'moderated_by',
    'moderated_at',
    'moderation_notes',
    'artisan_response_by',
    'artisan_response',
    'artisan_responded_at',
    'moderation_signal',
    'moderation_score',
    'moderation_metadata',
])]
class Review extends Model implements HasMedia
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, InteractsWithMedia;

    public const PROOF_COLLECTION = 'review_proof';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PROOF_COLLECTION)
            ->useDisk(MediaDisk::private())
            ->acceptsFile(fn (File $file): bool => in_array($file->mimeType, [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true));
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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
     * @return BelongsTo<User, $this>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function artisanResponseBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_response_by');
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * @return MorphMany<SupportCase, $this>
     */
    public function supportCases(): MorphMany
    {
        return $this->morphMany(SupportCase::class, 'supportable');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'artisan_responded_at' => 'datetime',
            'moderated_at' => 'datetime',
            'moderation_metadata' => 'array',
            'moderation_score' => 'integer',
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
            'status' => ReviewStatus::class,
        ];
    }
}
