<?php

namespace App\Models;

use App\Enums\AccountClaimStatus;
use Carbon\CarbonInterface;
use Database\Factories\AccountClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $claimed_by
 * @property string $token_hash
 * @property AccountClaimStatus $status
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $claimed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'user_id',
    'claimed_by',
    'token_hash',
    'status',
    'expires_at',
    'claimed_at',
    'metadata',
])]
class AccountClaim extends Model
{
    /** @use HasFactory<AccountClaimFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    /**
     * @param  Builder<AccountClaim>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', AccountClaimStatus::Pending);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'status' => AccountClaimStatus::class,
        ];
    }
}
