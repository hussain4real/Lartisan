<?php

namespace App\Models;

use App\Enums\ArtisanVerificationStatus;
use App\Enums\KycRiskLevel;
use Carbon\CarbonInterface;
use Database\Factories\KycSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property ArtisanVerificationStatus $status
 * @property KycRiskLevel|null $risk_level
 * @property CarbonInterface|null $submitted_at
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property string|null $decision_reason
 * @property int|null $reason_code_id
 * @property string|null $notes
 */
#[Fillable([
    'artisan_profile_id',
    'status',
    'risk_level',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'decision_reason',
    'reason_code_id',
    'notes',
])]
class KycSubmission extends Model implements HasMedia
{
    /** @use HasFactory<KycSubmissionFactory> */
    use HasFactory, InteractsWithMedia;

    public const GOVERNMENT_ID_COLLECTION = 'government_id';

    public const SELF_PORTRAIT_COLLECTION = 'self_portrait';

    public const ADDRESS_EVIDENCE_COLLECTION = 'address_evidence';

    public const BUSINESS_REGISTRATION_COLLECTION = 'business_registration';

    /**
     * @return array<int, string>
     */
    public static function mediaCollectionNames(): array
    {
        return [
            self::GOVERNMENT_ID_COLLECTION,
            self::SELF_PORTRAIT_COLLECTION,
            self::ADDRESS_EVIDENCE_COLLECTION,
            self::BUSINESS_REGISTRATION_COLLECTION,
        ];
    }

    public function registerMediaCollections(): void
    {
        foreach (self::mediaCollectionNames() as $collectionName) {
            $this->addMediaCollection($collectionName)
                ->useDisk('local')
                ->singleFile()
                ->acceptsFile(fn (File $file): bool => in_array($file->mimeType, [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ], true));
        }
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
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<ReasonCode, $this>
     */
    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    /**
     * @return HasMany<FieldVisit, $this>
     */
    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class);
    }

    /**
     * @return MorphMany<StatusHistory, $this>
     */
    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'statusable');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'risk_level' => KycRiskLevel::class,
            'status' => ArtisanVerificationStatus::class,
            'submitted_at' => 'datetime',
        ];
    }
}
