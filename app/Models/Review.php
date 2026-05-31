<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

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
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moderated_at' => 'datetime',
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
            'status' => ReviewStatus::class,
        ];
    }
}
