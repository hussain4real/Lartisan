<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Enums\WalletLedgerEntryType;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property int $payout_account_id
 * @property int $wallet_id
 * @property int|null $payout_batch_id
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property int|null $processed_by
 * @property PayoutStatus $status
 * @property int $amount
 * @property string $currency_code
 * @property Carbon $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $processing_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property string|null $provider_reference
 * @property string|null $provider_transfer_code
 * @property string|null $provider_status
 * @property Carbon|null $reconciled_at
 * @property Carbon|null $next_retry_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'artisan_profile_id',
    'payout_account_id',
    'wallet_id',
    'payout_batch_id',
    'requested_by',
    'approved_by',
    'processed_by',
    'status',
    'amount',
    'currency_code',
    'requested_at',
    'approved_at',
    'processing_at',
    'paid_at',
    'failed_at',
    'failure_reason',
    'provider_reference',
    'provider_transfer_code',
    'provider_status',
    'reconciled_at',
    'next_retry_at',
    'metadata',
])]
class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return BelongsTo<PayoutAccount, $this>
     */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<PayoutBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return HasMany<PayoutAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PayoutAttempt::class);
    }

    /**
     * @return MorphMany<WalletLedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(WalletLedgerEntry::class, 'source');
    }

    /**
     * @return MorphMany<SupportCase, $this>
     */
    public function supportCases(): MorphMany
    {
        return $this->morphMany(SupportCase::class, 'supportable');
    }

    public function hasReservedDebit(): bool
    {
        return $this->ledgerEntries()
            ->where('type', WalletLedgerEntryType::PayoutDebit)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'approved_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'next_retry_at' => 'datetime',
            'paid_at' => 'datetime',
            'processing_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'requested_at' => 'datetime',
            'status' => PayoutStatus::class,
        ];
    }
}
