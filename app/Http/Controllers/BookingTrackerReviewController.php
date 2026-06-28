<?php

namespace App\Http\Controllers;

use App\Actions\Reviews\SubmitVerifiedReview;
use App\Http\Controllers\Concerns\ResolvesBookingTracker;
use App\Http\Requests\BookingTracker\StoreGuestReviewRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BookingTrackerReviewController extends Controller
{
    use ResolvesBookingTracker;

    public function __invoke(
        StoreGuestReviewRequest $request,
        string $trackerCode,
        SubmitVerifiedReview $submitVerifiedReview,
    ): RedirectResponse {
        $booking = $this->bookingFromTracker($request, $trackerCode);

        $submitVerifiedReview->handle(
            booking: $booking,
            customer: null,
            rating: $request->integer('rating'),
            comment: $request->comment(),
            trackerToken: $this->trackerToken($request),
            proof: $request->proof(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review submitted.')]);

        return to_route('booking-tracker.show', [
            'trackerCode' => $trackerCode,
            'token' => $this->trackerToken($request),
        ]);
    }
}
