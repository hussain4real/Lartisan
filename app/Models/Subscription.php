<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property int $subscription_plan_id
 * @property int|null $payment_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $grace_ends_at
 */
#[Fillable([
    'artisan_profile_id',
    'subscription_plan_id',
    'payment_id',
    'status',
    'starts_at',
    'ends_at',
    'grace_ends_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'status' => SubscriptionStatus::class,
        ];
    }
}
