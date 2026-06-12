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
                name: 'Basic',
                slug: 'starter-listing',
                priceAmount: 500000,
                durationDays: 30,
                sortOrder: 10,
                features: ['Public listing', 'Customer lead access', 'Basic portfolio', 'Standard marketplace placement'],
            ),
            $this->upsertPlan(
                name: 'Pro',
                slug: 'growth-listing',
                priceAmount: 1250000,
                durationDays: 90,
                sortOrder: 20,
                features: ['Public listing', 'Priority lead access', 'Expanded portfolio', 'Team management', 'Quarterly visibility boost'],
                interval: SubscriptionInterval::Quarterly,
                includesTeamManagement: true,
            ),
            $this->upsertPlan(
                name: 'Premium',
                slug: 'annual-partner',
                priceAmount: 4500000,
                durationDays: 365,
                sortOrder: 30,
                features: ['Public listing', 'Priority lead access', 'Annual verification badge', 'Team management', 'Premium marketplace placement'],
                interval: SubscriptionInterval::Annual,
                includesTeamManagement: true,
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
        bool $includesTeamManagement = false,
    ): SubscriptionPlan {
        return SubscriptionPlan::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'active' => true,
                'currency_code' => 'NGN',
                'description' => 'Paid artisan listing activation for Lartisan marketplace visibility.',
                'duration_days' => $durationDays,
                'feature_summary' => $features,
                'includes_team_management' => $includesTeamManagement,
                'interval' => $interval,
                'name' => $name,
                'price_amount' => $priceAmount,
                'sort_order' => $sortOrder,
            ],
        );
    }
}
