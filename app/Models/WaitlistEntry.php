<?php

namespace App\Models;

use App\Enums\WaitlistAudienceType;
use Carbon\CarbonInterface;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property WaitlistAudienceType $audience_type
 * @property string|null $business_name
 * @property int|null $service_category_id
 * @property int $country_id
 * @property int|null $state_id
 * @property int|null $local_government_id
 * @property int|null $territory_id
 * @property string|null $note
 * @property bool $contact_consent
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'audience_type',
    'business_name',
    'service_category_id',
    'country_id',
    'state_id',
    'local_government_id',
    'territory_id',
    'note',
    'contact_consent',
])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience_type' => WaitlistAudienceType::class,
            'contact_consent' => 'boolean',
        ];
    }
}
