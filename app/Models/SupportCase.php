<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use Database\Factories\SupportCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $requester_id
 * @property int|null $owner_id
 * @property string|null $supportable_type
 * @property int|null $supportable_id
 * @property SupportCaseCategory $category
 * @property SupportCasePriority $priority
 * @property SupportCaseStatus $status
 * @property string $subject
 * @property string|null $description
 * @property string|null $resolution_notes
 * @property Carbon $opened_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'requester_id',
    'owner_id',
    'supportable_type',
    'supportable_id',
    'category',
    'priority',
    'status',
    'subject',
    'description',
    'resolution_notes',
    'opened_at',
    'resolved_at',
    'closed_at',
    'metadata',
])]
class SupportCase extends Model
{
    /** @use HasFactory<SupportCaseFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function supportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<SupportCaseNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(SupportCaseNote::class);
    }

    /**
     * @param  Builder<SupportCase>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return;
        }

        $visibleArtisanProfiles = ArtisanProfile::query()
            ->visibleTo($user)
            ->select('id');

        $query->where(function (Builder $query) use ($user, $visibleArtisanProfiles): void {
            $query
                ->where('owner_id', $user->id)
                ->orWhere('requester_id', $user->id)
                ->orWhere(function (Builder $query) use ($visibleArtisanProfiles): void {
                    $query
                        ->where('supportable_type', (new ArtisanProfile)->getMorphClass())
                        ->whereIn('supportable_id', clone $visibleArtisanProfiles);
                })
                ->orWhere(function (Builder $query) use ($visibleArtisanProfiles): void {
                    $query
                        ->where('supportable_type', (new Booking)->getMorphClass())
                        ->whereIn(
                            'supportable_id',
                            Booking::query()
                                ->whereIn('artisan_profile_id', clone $visibleArtisanProfiles)
                                ->select('id'),
                        );
                })
                ->orWhere(function (Builder $query) use ($visibleArtisanProfiles): void {
                    $query
                        ->where('supportable_type', (new Dispute)->getMorphClass())
                        ->whereIn(
                            'supportable_id',
                            Dispute::query()
                                ->whereIn('artisan_profile_id', clone $visibleArtisanProfiles)
                                ->select('id'),
                        );
                })
                ->orWhere(function (Builder $query) use ($visibleArtisanProfiles): void {
                    $query
                        ->where('supportable_type', (new Payout)->getMorphClass())
                        ->whereIn(
                            'supportable_id',
                            Payout::query()
                                ->whereIn('artisan_profile_id', clone $visibleArtisanProfiles)
                                ->select('id'),
                        );
                });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SupportCaseCategory::class,
            'closed_at' => 'datetime',
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'priority' => SupportCasePriority::class,
            'resolved_at' => 'datetime',
            'status' => SupportCaseStatus::class,
        ];
    }
}
