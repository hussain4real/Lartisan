<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Reviews\SubmitVerifiedReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreReviewRequest;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function store(
        StoreReviewRequest $request,
        Booking $booking,
        SubmitVerifiedReview $submitVerifiedReview,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $submitVerifiedReview->handle(
            booking: $booking,
            customer: $user,
            rating: $request->integer('rating'),
            comment: $request->string('comment')->trim()->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review submitted.')]);

        return to_route('customer.bookings.show', ['booking' => $booking]);
    }
}
