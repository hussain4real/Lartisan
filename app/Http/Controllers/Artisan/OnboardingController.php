<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Artisans\OnboardArtisanBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\StoreArtisanOnboardingRequest;
use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('artisan/Onboarding', [
            'existingProfiles' => $this->existingProfilesPayload($user),
            'geography' => $this->geographyPayload(),
        ]);
    }

    public function store(
        StoreArtisanOnboardingRequest $request,
        OnboardArtisanBusiness $onboardArtisanBusiness,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $profile = $onboardArtisanBusiness->handle(
            owner: $user,
            businessName: $request->businessName(),
            country: $request->country(),
            state: $request->state(),
            localGovernment: $request->localGovernment(),
            territory: $request->territory(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artisan business created.')]);

        return to_route('dashboard', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }

    /**
     * @return array<int, array{id: int, businessName: string, team: array{name: string, slug: string}, verificationStatus: string, subscriptionStatus: string, availabilityStatus: string, location: string}>
     */
    private function existingProfilesPayload(User $user): array
    {
        return $user->artisanProfiles()
            ->with(['team', 'country', 'state', 'localGovernment', 'territory'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ArtisanProfile $profile): array => $this->profilePayload($profile))
            ->all();
    }

    /**
     * @return array{id: int, businessName: string, team: array{name: string, slug: string}, verificationStatus: string, subscriptionStatus: string, availabilityStatus: string, location: string}
     */
    private function profilePayload(ArtisanProfile $profile): array
    {
        $team = $profile->team()->firstOrFail();

        return [
            'id' => $profile->id,
            'businessName' => $profile->business_name,
            'team' => [
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'verificationStatus' => $profile->verification_status->value,
            'subscriptionStatus' => $profile->subscription_status->value,
            'availabilityStatus' => $profile->availability_status->value,
            'location' => $this->profileLocation($profile),
        ];
    }

    private function profileLocation(ArtisanProfile $profile): string
    {
        return collect([
            $profile->territory?->name,
            $profile->localGovernment?->name,
            $profile->state?->name,
        ])->filter()->join(', ');
    }

    /**
     * @return array{countries: array<int, array{id: int, name: string, isoCode: string, states: array<int, array{id: int, name: string, slug: string, localGovernments: array<int, array{id: int, name: string, slug: string, territories: array<int, array{id: int, name: string, slug: string, type: string}>}>}>}>}
     */
    private function geographyPayload(): array
    {
        return [
            'countries' => Country::query()
                ->where('active', true)
                ->with(['states.localGovernments.territories'])
                ->orderBy('name')
                ->get()
                ->map(fn (Country $country): array => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'isoCode' => $country->iso_code,
                    'states' => $country->states
                        ->where('active', true)
                        ->sortBy('name')
                        ->values()
                        ->map(fn (State $state): array => [
                            'id' => $state->id,
                            'name' => $state->name,
                            'slug' => $state->slug,
                            'localGovernments' => $state->localGovernments
                                ->where('active', true)
                                ->sortBy('name')
                                ->values()
                                ->map(fn (LocalGovernment $localGovernment): array => [
                                    'id' => $localGovernment->id,
                                    'name' => $localGovernment->name,
                                    'slug' => $localGovernment->slug,
                                    'territories' => $localGovernment->territories
                                        ->where('active', true)
                                        ->sortBy('name')
                                        ->values()
                                        ->map(fn (Territory $territory): array => [
                                            'id' => $territory->id,
                                            'name' => $territory->name,
                                            'slug' => $territory->slug,
                                            'type' => $territory->type->value,
                                        ])
                                        ->all(),
                                ])
                                ->all(),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
