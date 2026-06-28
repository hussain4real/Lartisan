<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Booking;
use Illuminate\Http\Request;

trait ResolvesBookingTracker
{
    protected function bookingFromTracker(Request $request, string $trackerCode): Booking
    {
        $booking = Booking::query()
            ->with(['artisanProfile', 'artisanService.category', 'payments', 'review', 'disputes', 'statusHistories.actor'])
            ->where('tracker_code', $trackerCode)
            ->firstOrFail();

        abort_unless(hash_equals($booking->secure_token_hash, hash('sha256', $this->trackerToken($request))), 403);

        return $booking;
    }

    protected function trackerToken(Request $request): string
    {
        $token = $request->input('token', $request->query('token', ''));

        return is_scalar($token) ? (string) $token : '';
    }
}
