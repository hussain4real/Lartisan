<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Carbon\CarbonInterface;
use Database\Factories\OtpRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $phone_country_code
 * @property string|null $phone_number
 * @property string|null $phone_e164
 * @property string|null $email
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property int $max_attempts
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $verified_at
 * @property CarbonInterface|null $consumed_at
 * @property CarbonInterface $last_sent_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'user_id',
    'phone_country_code',
    'phone_number',
    'phone_e164',
    'email',
    'purpose',
    'code_hash',
    'attempts',
    'max_attempts',
    'expires_at',
    'verified_at',
    'consumed_at',
    'last_sent_at',
    'metadata',
])]
class OtpRecord extends Model
{
    /** @use HasFactory<OtpRecordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<OtpRecord>  $query
     */
    public function scopeForPhonePurpose(Builder $query, string $phoneE164, OtpPurpose $purpose): void
    {
        $query
            ->where('phone_e164', $phoneE164)
            ->where('purpose', $purpose);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasAttemptsRemaining(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'metadata' => 'array',
            'purpose' => OtpPurpose::class,
        ];
    }
}
