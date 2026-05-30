<?php

namespace App\Models;

use App\Enums\FieldVisitStatus;
use Carbon\CarbonInterface;
use Database\Factories\FieldVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int|null $kyc_submission_id
 * @property int $artisan_profile_id
 * @property int|null $area_agent_id
 * @property int|null $territory_id
 * @property FieldVisitStatus $status
 * @property CarbonInterface|null $visited_at
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $notes
 * @property array<string, mixed>|null $checklist
 */
#[Fillable([
    'kyc_submission_id',
    'artisan_profile_id',
    'area_agent_id',
    'territory_id',
    'status',
    'visited_at',
    'latitude',
    'longitude',
    'notes',
    'checklist',
])]
class FieldVisit extends Model
{
    /** @use HasFactory<FieldVisitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<KycSubmission, $this>
     */
    public function kycSubmission(): BelongsTo
    {
        return $this->belongsTo(KycSubmission::class);
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
    public function areaAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'area_agent_id');
    }

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
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
            'checklist' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => FieldVisitStatus::class,
            'visited_at' => 'datetime',
        ];
    }
}
