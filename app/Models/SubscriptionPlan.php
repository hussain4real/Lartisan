<?php

namespace App\Models;

use App\Enums\SubscriptionInterval;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_amount
 * @property string $currency_code
 * @property SubscriptionInterval $interval
 * @property int $duration_days
 * @property int $sort_order
 * @property bool $active
 * @property array<int, string>|null $feature_summary
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'price_amount',
    'currency_code',
    'interval',
    'duration_days',
    'sort_order',
    'active',
    'feature_summary',
])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'duration_days' => 'integer',
            'feature_summary' => 'array',
            'interval' => SubscriptionInterval::class,
            'price_amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
