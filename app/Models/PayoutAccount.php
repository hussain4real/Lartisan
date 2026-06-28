<?php

namespace App\Models;

use App\Enums\PaymentProviderName;
use App\Enums\PayoutAccountStatus;
use Database\Factories\PayoutAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property PaymentProviderName $provider
 * @property string $bank_code
 * @property string $bank_name
 * @property string $account_number
 * @property string $account_name
 * @property string|null $recipient_code
 * @property PayoutAccountStatus $status
 * @property Carbon|null $verified_at
 * @property Carbon|null $verification_checked_at
 * @property Carbon|null $recipient_registered_at
 * @property string|null $verification_provider_status
 * @property string|null $verification_failure_reason
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'artisan_profile_id',
    'provider',
    'bank_code',
    'bank_name',
    'account_number',
    'account_name',
    'recipient_code',
    'status',
    'verified_at',
    'verification_checked_at',
    'recipient_registered_at',
    'verification_provider_status',
    'verification_failure_reason',
    'metadata',
])]
class PayoutAccount extends Model
{
    /** @use HasFactory<PayoutAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return HasMany<Payout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'metadata' => 'array',
            'provider' => PaymentProviderName::class,
            'recipient_registered_at' => 'datetime',
            'status' => PayoutAccountStatus::class,
            'verification_checked_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
