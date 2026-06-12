<?php

namespace App\Http\Controllers;

use App\Enums\TeamKind;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function index(Request $request): Response
    {
        $plans = SubscriptionPlan::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Pricing', [
            'plans' => $plans
                ->map(fn (SubscriptionPlan $plan): array => $this->planPayload($plan))
                ->all(),
            'comparisonRows' => $this->comparisonRows(),
            'cta' => $this->ctaPayload($request->user()),
        ]);
    }

    /**
     * @return array{id: int, name: string, slug: string, badge: string|null, description: string, priceAmount: int, price: string, currencyCode: string, interval: string, durationDays: int, features: array<int, string>, includesTeamManagement: bool, highlighted: bool}
     */
    private function planPayload(SubscriptionPlan $plan): array
    {
        /** @var array<int, string> $features */
        $features = $plan->feature_summary ?? [];

        return [
            'id' => $plan->id,
            'name' => $this->displayName($plan),
            'slug' => $plan->slug,
            'badge' => $this->badge($plan),
            'description' => $this->description($plan),
            'priceAmount' => $plan->price_amount,
            'price' => number_format($plan->price_amount / 100, 2),
            'currencyCode' => $plan->currency_code,
            'interval' => $plan->interval->value,
            'durationDays' => $plan->duration_days,
            'features' => $features,
            'includesTeamManagement' => $plan->includes_team_management,
            'highlighted' => $plan->slug === 'growth-listing',
        ];
    }

    private function displayName(SubscriptionPlan $plan): string
    {
        return match ($plan->slug) {
            'starter-listing' => 'Basic',
            'growth-listing' => 'Pro',
            'annual-partner' => 'Premium',
            default => $plan->name,
        };
    }

    private function badge(SubscriptionPlan $plan): ?string
    {
        return match ($plan->slug) {
            'growth-listing' => 'Best for growing teams',
            'annual-partner' => 'Highest visibility',
            default => null,
        };
    }

    private function description(SubscriptionPlan $plan): string
    {
        return match ($plan->slug) {
            'starter-listing' => 'Start with a public listing, lead access, and a simple portfolio for individual artisan visibility.',
            'growth-listing' => 'Unlock stronger marketplace placement, team management, and expanded portfolio depth for growing businesses.',
            'annual-partner' => 'Get year-round visibility, partner signals, team management, and premium placement for established artisan brands.',
            default => $plan->description ?? 'Paid artisan listing activation for Lartisan marketplace visibility.',
        };
    }

    /**
     * @return array<int, array{label: string, values: array<string, string>}>
     */
    private function comparisonRows(): array
    {
        return [
            [
                'label' => 'Marketplace listing',
                'values' => [
                    'starter-listing' => 'Included',
                    'growth-listing' => 'Included',
                    'annual-partner' => 'Included',
                ],
            ],
            [
                'label' => 'Customer lead access',
                'values' => [
                    'starter-listing' => 'Standard',
                    'growth-listing' => 'Priority',
                    'annual-partner' => 'Priority',
                ],
            ],
            [
                'label' => 'Portfolio depth',
                'values' => [
                    'starter-listing' => 'Basic',
                    'growth-listing' => 'Expanded',
                    'annual-partner' => 'Expanded',
                ],
            ],
            [
                'label' => 'Marketplace placement',
                'values' => [
                    'starter-listing' => 'Standard',
                    'growth-listing' => 'Boosted',
                    'annual-partner' => 'Premium',
                ],
            ],
            [
                'label' => 'Team management',
                'values' => [
                    'starter-listing' => 'Not included',
                    'growth-listing' => 'Included',
                    'annual-partner' => 'Included',
                ],
            ],
            [
                'label' => 'Subscription duration',
                'values' => [
                    'starter-listing' => '30 days',
                    'growth-listing' => '90 days',
                    'annual-partner' => '365 days',
                ],
            ],
            [
                'label' => 'Partner signal',
                'values' => [
                    'starter-listing' => 'Not included',
                    'growth-listing' => 'Visibility boost',
                    'annual-partner' => 'Annual verification badge',
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, href: string}
     */
    private function ctaPayload(?User $user): array
    {
        $team = $user?->currentTeam()->first();

        if ($team instanceof Team && $team->kind === TeamKind::ArtisanBusiness && $team->artisanProfile()->exists()) {
            return [
                'label' => 'Manage subscription',
                'href' => route('artisan.subscription.show', ['current_team' => $team->slug]),
            ];
        }

        if ($team instanceof Team) {
            return [
                'label' => 'Become an artisan',
                'href' => route('artisan.onboarding.create', ['current_team' => $team->slug]),
            ];
        }

        return [
            'label' => 'Become an artisan',
            'href' => route('register', ['intent' => 'artisan']),
        ];
    }
}
