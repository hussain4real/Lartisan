<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property string $currency_code
 * @property int $available_balance
 * @property int $pending_balance
 */
#[Fillable([
    'artisan_profile_id',
    'currency_code',
    'available_balance',
    'pending_balance',
])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return HasMany<WalletLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_balance' => 'integer',
            'pending_balance' => 'integer',
        ];
    }
}
