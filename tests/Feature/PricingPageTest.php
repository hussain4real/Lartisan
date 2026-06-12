<?php

use App\Enums\TeamKind;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('pricing page exposes subscription tiers and comparison matrix', function () {
    $this->seed(SubscriptionPlanSeeder::class);

    $this
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Pricing')
            ->where('plans.0.name', 'Basic')
            ->where('plans.0.slug', 'starter-listing')
            ->where('plans.0.includesTeamManagement', false)
            ->where('plans.1.name', 'Pro')
            ->where('plans.1.slug', 'growth-listing')
            ->where('plans.1.includesTeamManagement', true)
            ->where('plans.2.name', 'Premium')
            ->where('plans.2.slug', 'annual-partner')
            ->where('plans.2.includesTeamManagement', true)
            ->where('comparisonRows.4.label', 'Team management')
            ->where('comparisonRows.4.values.starter-listing', 'Not included')
            ->where('comparisonRows.4.values.growth-listing', 'Included')
            ->where('comparisonRows.4.values.annual-partner', 'Included')
            ->where('cta.label', 'Become an artisan'));
});

test('pricing page sends artisan teams to subscription management', function () {
    $context = createTeamManagementContext();

    $this
        ->actingAs($context['owner'])
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Pricing')
            ->where('cta.label', 'Manage subscription')
            ->where('cta.href', route('artisan.subscription.show', ['current_team' => $context['team']->slug])));
});

test('pricing page sends signed in non artisans to onboarding', function () {
    $user = User::factory()->create();
    $team = $user->teams()->where('kind', TeamKind::Personal)->firstOrFail();

    $this
        ->actingAs($user)
        ->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Pricing')
            ->where('cta.label', 'Become an artisan')
            ->where('cta.href', route('artisan.onboarding.create', ['current_team' => $team->slug])));
});
