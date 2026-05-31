<?php

namespace App\Models;

use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

/**
 * @property int $id
 * @property int|null $booking_id
 * @property int|null $review_id
 * @property int|null $artisan_profile_id
 * @property int|null $customer_id
 * @property int|null $opened_by_id
 * @property int|null $assigned_to_id
 * @property int|null $resolved_by_id
 * @property DisputeStatus $status
 * @property DisputeSeverity $severity
 * @property string $subject
 * @property string|null $description
 * @property string|null $escalation_reason
 * @property string|null $resolution
 * @property Carbon $opened_at
 * @property Carbon|null $escalated_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'booking_id',
    'review_id',
    'artisan_profile_id',
    'customer_id',
    'opened_by_id',
    'assigned_to_id',
    'resolved_by_id',
    'status',
    'severity',
    'subject',
    'description',
    'escalation_reason',
    'resolution',
    'opened_at',
    'escalated_at',
    'resolved_at',
    'closed_at',
    'metadata',
])]
class Dispute extends Model implements HasMedia
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory, InteractsWithMedia;

    public const EVIDENCE_COLLECTION = 'dispute_evidence';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::EVIDENCE_COLLECTION)
            ->useDisk('local')
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
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
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
            'closed_at' => 'datetime',
            'escalated_at' => 'datetime',
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'severity' => DisputeSeverity::class,
            'status' => DisputeStatus::class,
        ];
    }
}
