<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Artisans\UpsertArtisanProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\StoreArtisanPortfolioMediaRequest;
use App\Http\Requests\Artisan\UpdateArtisanProfileRequest;
use App\Models\ArtisanProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProfileController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function edit(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('update', $profile);

        return Inertia::render('artisan/Profile', [
            'profile' => $this->profilePayload($profile),
            'availabilityStatuses' => [
                'online',
                'busy',
                'offline',
                'vacation',
            ],
        ]);
    }

    public function update(
        UpdateArtisanProfileRequest $request,
        UpsertArtisanProfile $upsertArtisanProfile,
    ): RedirectResponse {
        $profile = $this->artisanProfileFrom($request);
        $user = $this->userFrom($request);

        Gate::authorize('update', $profile);

        $updatedProfile = $upsertArtisanProfile->handle(
            profile: $profile,
            actor: $user,
            businessName: $request->businessName(),
            publicSummary: $request->publicSummary(),
            yearsExperience: $request->yearsExperience(),
            serviceRadiusKm: $request->serviceRadiusKm(),
            publicPhone: $request->publicPhone(),
            publicEmail: $request->publicEmail(),
            availabilityStatus: $request->availabilityStatus(),
            isPublic: $request->isPublic(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('artisan.profile.edit', ['current_team' => $updatedProfile->team()->firstOrFail()->slug]);
    }

    public function portfolio(StoreArtisanPortfolioMediaRequest $request): RedirectResponse
    {
        $profile = $this->artisanProfileFrom($request);
        $user = $this->userFrom($request);

        Gate::authorize('update', $profile);

        $profile
            ->addMedia($request->portfolioImage())
            ->withCustomProperties(['uploaded_by' => $user->id])
            ->toMediaCollection(ArtisanProfile::PORTFOLIO_COLLECTION);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Portfolio image added.')]);

        return to_route('artisan.profile.edit', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }

    /**
     * @return array{id: int, businessName: string, publicSummary: string|null, yearsExperience: int|null, serviceRadiusKm: int|null, publicPhone: string|null, publicEmail: string|null, verificationStatus: string, subscriptionStatus: string, availabilityStatus: string, isPublic: bool, portfolio: array<int, array{id: int, name: string, fileName: string, url: string}>}
     */
    private function profilePayload(ArtisanProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'businessName' => $profile->business_name,
            'publicSummary' => $profile->public_summary,
            'yearsExperience' => $profile->years_experience,
            'serviceRadiusKm' => $profile->service_radius_km,
            'publicPhone' => $profile->public_phone,
            'publicEmail' => $profile->public_email,
            'verificationStatus' => $profile->verification_status->value,
            'subscriptionStatus' => $profile->subscription_status->value,
            'availabilityStatus' => $profile->availability_status->value,
            'isPublic' => $profile->is_public,
            'portfolio' => $profile
                ->getMedia(ArtisanProfile::PORTFOLIO_COLLECTION)
                ->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'fileName' => $media->file_name,
                    'url' => $media->getFullUrl(),
                ])
                ->all(),
        ];
    }
}
