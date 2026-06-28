<?php

namespace App\Http\Controllers;

use App\Actions\Disputes\OpenGuestDispute;
use App\Http\Controllers\Concerns\ResolvesBookingTracker;
use App\Http\Requests\BookingTracker\StoreGuestDisputeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BookingTrackerDisputeController extends Controller
{
    use ResolvesBookingTracker;

    public function __invoke(
        StoreGuestDisputeRequest $request,
        string $trackerCode,
        OpenGuestDispute $openGuestDispute,
    ): RedirectResponse {
        $booking = $this->bookingFromTracker($request, $trackerCode);
        $reviewId = $request->integer('review_id') ?: null;
        $review = $reviewId === null ? null : $booking->review()->whereKey($reviewId)->firstOrFail();
        $paymentId = $request->paymentId();
        $payment = $paymentId === null ? null : $booking->payments()->whereKey($paymentId)->firstOrFail();

        $openGuestDispute->handle(
            booking: $booking,
            trackerToken: $this->trackerToken($request),
            subject: $request->subject(),
            description: $request->description(),
            severity: $request->severity(),
            review: $review,
            evidence: $request->evidence(),
            payment: $payment,
            target: $request->target(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dispute opened.')]);

        return to_route('booking-tracker.show', [
            'trackerCode' => $trackerCode,
            'token' => $this->trackerToken($request),
        ]);
    }
}
