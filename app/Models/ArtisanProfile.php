<?php

namespace App\Models;

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use Database\Factories\ArtisanProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $business_name
 * @property ArtisanVerificationStatus $verification_status
 * @property ArtisanSubscriptionStatus $subscription_status
 * @property ArtisanAvailabilityStatus $availability_status
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $local_government_id
 * @property int|null $territory_id
 * @property int|null $onboarded_by_agent_id
 * @property int|null $approved_by
 * @property bool $is_public
 */
#[Fillable([
    'team_id',
    'user_id',
    'business_name',
    'verification_status',
    'subscription_status',
    'availability_status',
    'country_id',
    'state_id',
    'local_government_id',
    'territory_id',
    'onboarded_by_agent_id',
    'approved_by',
    'approved_at',
    'is_public',
    'internal_notes',
])]
class ArtisanProfile extends Model
{
    /** @use HasFactory<ArtisanProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * @return BelongsTo<User, $this>
     */
    public function onboardedByAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onboarded_by_agent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'availability_status' => ArtisanAvailabilityStatus::class,
            'is_public' => 'boolean',
            'subscription_status' => ArtisanSubscriptionStatus::class,
            'verification_status' => ArtisanVerificationStatus::class,
        ];
    }
}
