<?php

namespace App\Models;

use App\Enums\PayoutBatchStatus;
use Database\Factories\PayoutBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PayoutBatchStatus $status
 * @property Carbon $scheduled_for
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int $total_payouts
 * @property int $successful_payouts
 * @property int $failed_payouts
 * @property int $action_required_payouts
 * @property int $total_amount
 * @property string $currency_code
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'status',
    'scheduled_for',
    'started_at',
    'completed_at',
    'total_payouts',
    'successful_payouts',
    'failed_payouts',
    'action_required_payouts',
    'total_amount',
    'currency_code',
    'created_by',
    'metadata',
])]
class PayoutBatch extends Model
{
    /** @use HasFactory<PayoutBatchFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
            'action_required_payouts' => 'integer',
            'completed_at' => 'datetime',
            'failed_payouts' => 'integer',
            'metadata' => 'array',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'status' => PayoutBatchStatus::class,
            'successful_payouts' => 'integer',
            'total_amount' => 'integer',
            'total_payouts' => 'integer',
        ];
    }
}
