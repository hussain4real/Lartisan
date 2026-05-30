<?php

namespace App\Actions\Artisans;

use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OnboardArtisanBusiness
{
    public function __construct(
        private readonly CreateArtisanBusinessProfile $createArtisanBusinessProfile,
        private readonly UpdateArtisanBusinessLocation $updateArtisanBusinessLocation,
    ) {}

    public function handle(
        User $owner,
        string $businessName,
        Country $country,
        State $state,
        LocalGovernment $localGovernment,
        ?Territory $territory = null,
    ): ArtisanProfile {
        return DB::transaction(function () use ($owner, $businessName, $country, $state, $localGovernment, $territory): ArtisanProfile {
            $profile = $this->createArtisanBusinessProfile->handle(
                owner: $owner,
                businessName: $businessName,
            );

            return $this->updateArtisanBusinessLocation->handle(
                profile: $profile,
                country: $country,
                state: $state,
                localGovernment: $localGovernment,
                territory: $territory,
            );
        });
    }
}
