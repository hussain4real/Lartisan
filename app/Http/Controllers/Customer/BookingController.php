<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('customer/Bookings', [
            'bookings' => $user->customerBookings()
                ->with(['artisanProfile', 'artisanService.category'])
                ->latest('id')
                ->get()
                ->map(fn (Booking $booking): array => $this->bookingCardPayload($booking))
                ->all(),
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        $this->authorizeCustomer($request, $booking);

        return Inertia::render('customer/BookingShow', [
            'booking' => $this->bookingCardPayload($booking->load(['artisanProfile', 'artisanService.category'])),
        ]);
    }

    public function confirm(
        Request $request,
        Booking $booking,
        ConfirmBookingCompletion $confirmBookingCompletion,
    ): RedirectResponse {
        $this->authorizeCustomer($request, $booking);
        $confirmBookingCompletion->handle($booking, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking completion confirmed.')]);

        return to_route('customer.bookings.show', ['booking' => $booking]);
    }

    private function authorizeCustomer(Request $request, Booking $booking): void
    {
        abort_unless($booking->customer_id === $this->user($request)->id, 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    /**
     * @return array{id: int, status: string, customerName: string, scheduledAt: string|null, quotedAmountDisplay: string|null, currencyCode: string, artisan: array{id: int, businessName: string}, service: array{id: int, title: string, category: string}|null, trackerCode: string}
     */
    private function bookingCardPayload(Booking $booking): array
    {
        $service = $booking->artisanService()->first();

        return [
            'id' => $booking->id,
            'status' => $booking->status->value,
            'customerName' => $booking->customer_name,
            'scheduledAt' => $booking->scheduled_at?->toISOString(),
            'quotedAmountDisplay' => $booking->quoted_amount === null ? null : number_format($booking->quoted_amount / 100, 2),
            'currencyCode' => $booking->currency_code,
            'trackerCode' => $booking->tracker_code,
            'artisan' => [
                'id' => $booking->artisanProfile()->firstOrFail()->id,
                'businessName' => $booking->artisanProfile()->firstOrFail()->business_name,
            ],
            'service' => $service === null ? null : [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category()->firstOrFail()->name,
            ],
        ];
    }
}
