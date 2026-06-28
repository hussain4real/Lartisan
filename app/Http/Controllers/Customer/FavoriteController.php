<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FavoriteController extends Controller
{
    public function store(Request $request, ArtisanProfile $artisanProfile): RedirectResponse
    {
        abort_unless($this->isVisibleMarketplaceProfile($artisanProfile), 404);

        $this->user($request)->customerFavorites()->firstOrCreate([
            'artisan_profile_id' => $artisanProfile->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artisan saved to favorites.')]);

        return back();
    }

    public function destroy(Request $request, ArtisanProfile $artisanProfile): RedirectResponse
    {
        $this->user($request)
            ->customerFavorites()
            ->where('artisan_profile_id', $artisanProfile->id)
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artisan removed from favorites.')]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    private function isVisibleMarketplaceProfile(ArtisanProfile $profile): bool
    {
        return $profile->verification_status === ArtisanVerificationStatus::Approved
            && $profile->subscription_status === ArtisanSubscriptionStatus::Active
            && $profile->availability_status !== ArtisanAvailabilityStatus::Vacation
            && $profile->is_public
            && $profile->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '>', now())
                ->exists();
    }
}
