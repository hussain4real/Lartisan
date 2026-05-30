<?php

namespace App\Actions\Artisans;

use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use InvalidArgumentException;

class UpdateArtisanBusinessLocation
{
    /**
     * Update a business profile's resolved operating geography.
     */
    public function handle(
        ArtisanProfile $profile,
        Country $country,
        State $state,
        LocalGovernment $localGovernment,
        ?Territory $territory = null,
    ): ArtisanProfile {
        $this->ensureHierarchy($country, $state, $localGovernment, $territory);

        $profile->update([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'local_government_id' => $localGovernment->id,
            'territory_id' => $territory?->id,
        ]);

        return $profile->refresh();
    }

    private function ensureHierarchy(
        Country $country,
        State $state,
        LocalGovernment $localGovernment,
        ?Territory $territory,
    ): void {
        if ($state->country_id !== $country->id) {
            throw new InvalidArgumentException('The selected state does not belong to the selected country.');
        }

        if ($localGovernment->state_id !== $state->id) {
            throw new InvalidArgumentException('The selected local government does not belong to the selected state.');
        }

        if ($territory instanceof Territory && $territory->local_government_id !== $localGovernment->id) {
            throw new InvalidArgumentException('The selected territory does not belong to the selected local government.');
        }
    }
}
