<?php

namespace App\Models;

use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $artisan_profile_id
 * @property int|null $booking_id
 * @property int|null $subscription_plan_id
 * @property int|null $subscription_id
 * @property PaymentProviderName $provider
 * @property PaymentPurpose $purpose
 * @property PaymentStatus $status
 * @property string $reference
 * @property string|null $provider_reference
 * @property int $amount
 * @property string $currency_code
 * @property int|null $commission_basis_points
 * @property int|null $commission_amount
 * @property int|null $provider_fee_basis_points
 * @property int|null $provider_fee_flat_amount
 * @property int|null $provider_fee_amount
 * @property int|null $net_amount
 * @property string|null $checkout_url
 * @property string|null $access_code
 * @property array<string, mixed>|null $provider_payload
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 */
#[Fillable([
    'artisan_profile_id',
    'booking_id',
    'subscription_plan_id',
    'subscription_id',
    'provider',
    'purpose',
    'status',
    'reference',
    'provider_reference',
    'amount',
    'currency_code',
    'commission_basis_points',
    'commission_amount',
    'provider_fee_basis_points',
    'provider_fee_flat_amount',
    'provider_fee_amount',
    'net_amount',
    'checkout_url',
    'access_code',
    'provider_payload',
    'paid_at',
    'failed_at',
    'failure_reason',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ArtisanProfile, $this>
     */
    public function artisanProfile(): BelongsTo
    {
        return $this->belongsTo(ArtisanProfile::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'commission_amount' => 'integer',
            'commission_basis_points' => 'integer',
            'failed_at' => 'datetime',
            'net_amount' => 'integer',
            'paid_at' => 'datetime',
            'provider' => PaymentProviderName::class,
            'provider_fee_amount' => 'integer',
            'provider_fee_basis_points' => 'integer',
            'provider_fee_flat_amount' => 'integer',
            'provider_payload' => 'array',
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
        ];
    }
}
