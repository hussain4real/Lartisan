<?php

use App\Enums\TeamRole;
use App\Models\ArtisanProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * @param  array<string, mixed>  $planAttributes
 * @param  array<string, mixed>  $subscriptionAttributes
 * @return array{owner: User, team: Team, profile: ArtisanProfile, plan: SubscriptionPlan, subscription: Subscription}
 */
function createTeamManagementContext(array $planAttributes = [], array $subscriptionAttributes = []): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->artisanBusiness()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $profile = ArtisanProfile::factory()->create([
        'team_id' => $team->id,
        'user_id' => $owner->id,
    ]);
    $plan = SubscriptionPlan::factory()->create([
        'active' => true,
        'includes_team_management' => true,
        ...$planAttributes,
    ]);
    $subscription = Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
        ...$subscriptionAttributes,
    ]);

    return [
        'owner' => $owner->refresh(),
        'team' => $team->refresh(),
        'profile' => $profile->refresh(),
        'plan' => $plan->refresh(),
        'subscription' => $subscription->refresh(),
    ];
}
