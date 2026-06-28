<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Reviews\RespondToReview;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReviewResponseController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function __invoke(
        Request $request,
        string $currentTeam,
        Review $review,
        RespondToReview $respondToReview,
    ): RedirectResponse {
        $profile = $this->artisanProfileFrom($request);
        Gate::authorize('view', $profile);
        abort_unless($review->artisan_profile_id === $profile->id, 404);

        $request->validate([
            'response' => ['required', 'string', 'max:1200'],
        ]);

        $user = $request->user();
        assert($user instanceof User);

        $respondToReview->handle(
            review: $review,
            actor: $user,
            response: $request->string('response')->trim()->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review response saved.')]);

        return to_route('artisan.bookings.index', ['current_team' => $currentTeam]);
    }
}
