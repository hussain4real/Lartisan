<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\UpgradeGuestBookingAccount;
use App\Http\Controllers\Concerns\ResolvesBookingTracker;
use App\Http\Requests\BookingTracker\UpgradeGuestBookingAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BookingTrackerAccountController extends Controller
{
    use ResolvesBookingTracker;

    public function __invoke(
        UpgradeGuestBookingAccountRequest $request,
        string $trackerCode,
        UpgradeGuestBookingAccount $upgradeGuestBookingAccount,
    ): RedirectResponse {
        $booking = $this->bookingFromTracker($request, $trackerCode);
        $user = $upgradeGuestBookingAccount->handle(
            booking: $booking,
            trackerToken: $this->trackerToken($request),
            name: $request->guestName(),
            email: $request->email(),
            password: $request->password(),
        );

        Auth::login($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your customer account is ready.')]);

        return to_route('customer.bookings.show', ['booking' => $booking->refresh()]);
    }
}
