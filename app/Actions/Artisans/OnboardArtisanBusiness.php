<?php

namespace App\Actions\Artisans;

use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;

class OnboardArtisanBusiness
{
    public function __construct(
        private readonly CreateArtisanBusinessWorkspace $createArtisanBusinessWorkspace,
    ) {}

    public function handle(
        User $owner,
        string $businessName,
        Country $country,
        State $state,
        LocalGovernment $localGovernment,
        ?Territory $territory = null,
    ): ArtisanProfile {
        return $this->createArtisanBusinessWorkspace->handle(
            owner: $owner,
            businessName: $businessName,
            country: $country,
            state: $state,
            localGovernment: $localGovernment,
            territory: $territory,
        );
    }
}
