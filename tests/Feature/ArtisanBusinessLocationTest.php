<?php

use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use Database\Seeders\GeographySeeder;

test('artisan business location action stores a valid geography hierarchy', function () {
    $this->seed(GeographySeeder::class);

    $profile = ArtisanProfile::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $territory = Territory::query()->where('slug', 'wuse-market')->firstOrFail();

    $updated = app(UpdateArtisanBusinessLocation::class)->handle(
        $profile,
        $country,
        $state,
        $localGovernment,
        $territory,
    );

    expect($updated->country->is($country))->toBeTrue();
    expect($updated->state->is($state))->toBeTrue();
    expect($updated->localGovernment->is($localGovernment))->toBeTrue();
    expect($updated->territory->is($territory))->toBeTrue();
    expect($country->artisanProfiles()->firstOrFail()->is($updated))->toBeTrue();
    expect($state->artisanProfiles()->firstOrFail()->is($updated))->toBeTrue();
    expect($localGovernment->artisanProfiles()->firstOrFail()->is($updated))->toBeTrue();
    expect($territory->artisanProfiles()->firstOrFail()->is($updated))->toBeTrue();
});

test('artisan business location can resolve to an lga without a territory', function () {
    $this->seed(GeographySeeder::class);

    $profile = ArtisanProfile::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();

    $updated = app(UpdateArtisanBusinessLocation::class)->handle($profile, $country, $state, $localGovernment);

    expect($updated->country_id)->toBe($country->id);
    expect($updated->state_id)->toBe($state->id);
    expect($updated->local_government_id)->toBe($localGovernment->id);
    expect($updated->territory_id)->toBeNull();
    expect($updated->territory()->exists())->toBeFalse();
});

test('artisan business location rejects mismatched geography hierarchy', function () {
    $this->seed(GeographySeeder::class);

    $profile = ArtisanProfile::factory()->create();
    $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
    $otherCountry = Country::factory()->create(['iso_code' => 'GH']);
    $fct = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $lagos = State::query()->where('slug', 'lagos')->firstOrFail();
    $amac = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $abajiTerritory = Territory::query()->where('slug', 'abaji-market')->firstOrFail();

    expect(fn () => app(UpdateArtisanBusinessLocation::class)->handle($profile, $otherCountry, $fct, $amac))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(UpdateArtisanBusinessLocation::class)->handle($profile, $country, $lagos, $amac))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(UpdateArtisanBusinessLocation::class)->handle($profile, $country, $fct, $amac, $abajiTerritory))
        ->toThrow(InvalidArgumentException::class);
});
