<?php

namespace App\Http\Controllers;

use App\Actions\Payments\InitializeBookingPayment;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class BookingPaymentController extends Controller
{
    public function customer(Request $request, Booking $booking, InitializeBookingPayment $initializeBookingPayment): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        abort_unless($booking->customer_id === $user->id, 403);

        $payment = $initializeBookingPayment->handle(
            booking: $booking,
            callbackUrl: route('customer.bookings.show', ['booking' => $booking]),
        );

        assert($payment->checkout_url !== null);

        return Inertia::location($payment->checkout_url);
    }

    public function tracker(
        Request $request,
        string $trackerCode,
        InitializeBookingPayment $initializeBookingPayment,
    ): Response {
        $booking = Booking::query()
            ->where('tracker_code', $trackerCode)
            ->firstOrFail();
        $token = $this->trackerToken($request);

        abort_unless(hash_equals($booking->secure_token_hash, hash('sha256', $token)), 403);

        $payment = $initializeBookingPayment->handle(
            booking: $booking,
            callbackUrl: route('booking-tracker.show', ['trackerCode' => $trackerCode, 'token' => $token]),
        );

        assert($payment->checkout_url !== null);

        return Inertia::location($payment->checkout_url);
    }

    private function trackerToken(Request $request): string
    {
        $token = $request->input('token', $request->query('token', ''));

        return is_scalar($token) ? (string) $token : '';
    }
}
