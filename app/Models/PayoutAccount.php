<?php

namespace App\Models;

use App\Enums\PaymentProviderName;
use App\Enums\PayoutAccountStatus;
use Database\Factories\PayoutAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'metadata' => 'array',
            'provider' => PaymentProviderName::class,
            'status' => PayoutAccountStatus::class,
            'verified_at' => 'datetime',
        ];
    }
}
