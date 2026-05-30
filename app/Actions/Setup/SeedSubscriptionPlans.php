<?php

namespace App\Actions\Setup;

use App\Enums\SubscriptionInterval;
use App\Models\SubscriptionPlan;

class SeedSubscriptionPlans
{
    /**
     * Seed the paid listing plans artisans can use to activate marketplace visibility.
     *
     * @return array<int, SubscriptionPlan>
     */
    public function handle(): array
    {
        return [
            $this->upsertPlan(
                name: 'Starter Listing',
                slug: 'starter-listing',
                priceAmount: 500000,
                durationDays: 30,
                sortOrder: 10,
                features: ['Public listing', 'Customer lead access', 'Basic portfolio'],
            ),
            $this->upsertPlan(
                name: 'Growth Listing',
                slug: 'growth-listing',
                priceAmount: 1250000,
                durationDays: 90,
                sortOrder: 20,
                features: ['Public listing', 'Priority lead access', 'Expanded portfolio'],
                interval: SubscriptionInterval::Quarterly,
            ),
            $this->upsertPlan(
                name: 'Annual Partner',
                slug: 'annual-partner',
                priceAmount: 4500000,
                durationDays: 365,
                sortOrder: 30,
                features: ['Public listing', 'Priority lead access', 'Annual verification badge'],
                interval: SubscriptionInterval::Annual,
            ),
        ];
    }

    /**
     * @param  array<int, string>  $features
     */
    private function upsertPlan(
        string $name,
        string $slug,
        int $priceAmount,
        int $durationDays,
        int $sortOrder,
        array $features,
        SubscriptionInterval $interval = SubscriptionInterval::Monthly,
    ): SubscriptionPlan {
        return SubscriptionPlan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'active' => true,
                'currency_code' => 'NGN',
                'description' => 'Paid artisan listing activation for Lartisan marketplace visibility.',
                'duration_days' => $durationDays,
                'feature_summary' => $features,
                'interval' => $interval,
                'name' => $name,
                'price_amount' => $priceAmount,
                'sort_order' => $sortOrder,
            ],
        );
    }
}
