<?php

namespace App\Actions\Artisans;

use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateArtisanBusinessWorkspace
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
        ?User $onboardedByAgent = null,
        ?string $internalNotes = null,
    ): ArtisanProfile {
        return DB::transaction(function () use ($owner, $businessName, $country, $state, $localGovernment, $territory, $onboardedByAgent, $internalNotes): ArtisanProfile {
            $profile = $this->createArtisanBusinessProfile->handle(
                owner: $owner,
                businessName: $businessName,
                onboardedByAgent: $onboardedByAgent,
                internalNotes: $internalNotes,
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
