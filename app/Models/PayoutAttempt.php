<?php

namespace App\Models;

use App\Enums\PayoutAttemptStatus;
use Database\Factories\PayoutAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payout_id
 * @property int $attempt_number
 * @property PayoutAttemptStatus $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $provider_payload
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'payout_id',
    'attempt_number',
    'status',
    'provider_reference',
    'failure_reason',
    'provider_payload',
    'processed_at',
])]
class PayoutAttempt extends Model
{
    /** @use HasFactory<PayoutAttemptFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Payout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'processed_at' => 'datetime',
            'provider_payload' => 'array',
            'status' => PayoutAttemptStatus::class,
        ];
    }
}
