<?php

use App\Actions\Artisans\OnboardArtisanBusiness;
use App\Enums\PlatformRole;
use App\Http\Controllers\Artisan\OnboardingController;
use App\Http\Requests\Artisan\StoreArtisanOnboardingRequest;
use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(GeographySeeder::class);
    $this->seed(PlatformAccessSeeder::class);
});

test('artisan onboarding page renders geography and existing businesses', function () {
    $user = User::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();

    $profile = ArtisanProfile::factory()->create([
        'user_id' => $user->id,
        'business_name' => 'Wuse Sparks Electrical',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $localGovernment->id,
        'territory_id' => $territory->id,
    ]);
    $profileTeam = $profile->team()->firstOrFail();

    $this
        ->actingAs($user)
        ->get(route('artisan.onboarding.create', ['current_team' => $user->currentTeam]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Onboarding')
            ->has('geography.countries', 1)
            ->where('geography.countries.0.isoCode', 'NG')
            ->has('geography.countries.0.states', 37)
            ->has('existingProfiles', 1)
            ->where('existingProfiles.0.id', $profile->id)
            ->where('existingProfiles.0.businessName', 'Wuse Sparks Electrical')
            ->where('existingProfiles.0.team.name', $profileTeam->name)
            ->where('existingProfiles.0.location', 'Wuse Market, Abuja Municipal Area Council, Federal Capital Territory')
            ->etc());
});

test('artisan onboarding creates a business workspace and profile with territory', function () {
    $user = User::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();

    $this
        ->actingAs($user)
        ->post(route('artisan.onboarding.store', ['current_team' => $user->currentTeam]), [
            'business_name' => ' Bright Sparks Electrical ',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_id' => $localGovernment->id,
            'territory_id' => $territory->id,
        ])
        ->assertRedirect();

    $profile = ArtisanProfile::query()->where('business_name', 'Bright Sparks Electrical')->firstOrFail();
    $profileUser = $profile->user()->firstOrFail();
    $profileTeam = $profile->team()->firstOrFail();
    $profileCountry = $profile->country()->firstOrFail();
    $profileState = $profile->state()->firstOrFail();
    $profileLocalGovernment = $profile->localGovernment()->firstOrFail();
    $profileTerritory = $profile->territory()->firstOrFail();
    $freshUser = User::query()->findOrFail($user->id);

    expect($profileUser->is($user))->toBeTrue();
    expect($profileTeam->is_personal)->toBeFalse();
    expect($profileCountry->is($country))->toBeTrue();
    expect($profileState->is($state))->toBeTrue();
    expect($profileLocalGovernment->is($localGovernment))->toBeTrue();
    expect($profileTerritory->is($territory))->toBeTrue();
    expect($freshUser->current_team_id)->toBe($profile->team_id);
    expect($freshUser->hasRole(PlatformRole::Artisan->value))->toBeTrue();
});

test('artisan onboarding can resolve to an lga without territory', function () {
    $user = User::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();

    $this
        ->actingAs($user)
        ->post(route('artisan.onboarding.store', ['current_team' => $user->currentTeam]), [
            'business_name' => 'AMAC Paint Studio',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_id' => $localGovernment->id,
        ])
        ->assertRedirect();

    $profile = ArtisanProfile::query()->where('business_name', 'AMAC Paint Studio')->firstOrFail();

    expect($profile->country_id)->toBe($country->id);
    expect($profile->state_id)->toBe($state->id);
    expect($profile->local_government_id)->toBe($localGovernment->id);
    expect($profile->territory_id)->toBeNull();
});

test('artisan onboarding rejects a state outside the selected country', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create(['iso_code' => 'GH']);
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();

    $this
        ->actingAs($user)
        ->post(route('artisan.onboarding.store', ['current_team' => $user->currentTeam]), [
            'business_name' => 'Boundary Test Electrical',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_id' => $localGovernment->id,
        ])
        ->assertSessionHasErrors('state_id');

    expect(ArtisanProfile::query()->count())->toBe(0);
});

test('artisan onboarding rejects an lga outside the selected state', function () {
    $user = User::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'lagos')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();

    $this
        ->actingAs($user)
        ->post(route('artisan.onboarding.store', ['current_team' => $user->currentTeam]), [
            'business_name' => 'LGA Boundary Test',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_id' => $localGovernment->id,
        ])
        ->assertSessionHasErrors('local_government_id');

    expect(ArtisanProfile::query()->count())->toBe(0);
});

test('artisan onboarding rejects a territory outside the selected lga', function () {
    $user = User::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $territory = Territory::query()->where('slug', 'abaji-market')->firstOrFail();

    $this
        ->actingAs($user)
        ->post(route('artisan.onboarding.store', ['current_team' => $user->currentTeam]), [
            'business_name' => 'Territory Boundary Test',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_id' => $localGovernment->id,
            'territory_id' => $territory->id,
        ])
        ->assertSessionHasErrors('territory_id');

    expect(ArtisanProfile::query()->count())->toBe(0);
});

test('artisan onboarding controller guards direct create calls without a user', function () {
    expect(fn () => app(OnboardingController::class)->create(Request::create('/artisan/onboarding')))
        ->toThrow(HttpException::class);
});

test('artisan onboarding controller guards direct store calls without a user', function () {
    expect(fn () => app(OnboardingController::class)->store(
        StoreArtisanOnboardingRequest::create('/artisan/onboarding', 'POST'),
        app(OnboardArtisanBusiness::class),
    ))->toThrow(HttpException::class);
});
