<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Bookings\PostBookingMessage;
use App\Enums\BookingMessageSenderRole;
use App\Http\Controllers\Concerns\BuildsBookingChatPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingMessageRequest;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingChatController extends Controller
{
    use BuildsBookingChatPayload;
    use ResolvesCurrentArtisanProfile;

    public function show(Request $request, string $currentTeam, Booking $booking): Response
    {
        $user = $this->userFrom($request);
        $this->authorizeArtisanBooking($request, $booking);

        return Inertia::render('booking/Chat', $this->bookingChatPayload(
            booking: $booking,
            viewer: $user,
            viewerRole: BookingMessageSenderRole::Artisan,
            backUrl: route('artisan.bookings.index', ['current_team' => $currentTeam]),
            currentTeamSlug: $currentTeam,
        ));
    }

    public function store(
        StoreBookingMessageRequest $request,
        string $currentTeam,
        Booking $booking,
        PostBookingMessage $postBookingMessage,
    ): RedirectResponse {
        $user = $this->user($request);
        $this->authorizeArtisanBooking($request, $booking);
        $postBookingMessage->handle($booking, $user, $request->body());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message sent.')]);

        return back();
    }

    private function authorizeArtisanBooking(Request $request, Booking $booking): void
    {
        $profile = $this->artisanProfileFrom($request);
        Gate::authorize('view', $profile);
        abort_unless($booking->customer_id !== null, 404);
        abort_unless($booking->artisan_profile_id === $profile->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
