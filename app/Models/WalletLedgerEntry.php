<?php

namespace App\Models;

use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use Database\Factories\WalletLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $wallet_id
 * @property WalletLedgerEntryType $type
 * @property WalletLedgerDirection $direction
 * @property int $amount
 * @property int $available_balance_after
 * @property int $pending_balance_after
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $immutable_reference
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon $posted_at
 */
#[Fillable([
    'wallet_id',
    'type',
    'direction',
    'amount',
    'available_balance_after',
    'pending_balance_after',
    'source_type',
    'source_id',
    'immutable_reference',
    'description',
    'metadata',
    'posted_at',
])]
class WalletLedgerEntry extends Model
{
    /** @use HasFactory<WalletLedgerEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Wallet ledger entries are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Wallet ledger entries are immutable.'));
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'available_balance_after' => 'integer',
            'direction' => WalletLedgerDirection::class,
            'metadata' => 'array',
            'pending_balance_after' => 'integer',
            'posted_at' => 'datetime',
            'type' => WalletLedgerEntryType::class,
        ];
    }
}
