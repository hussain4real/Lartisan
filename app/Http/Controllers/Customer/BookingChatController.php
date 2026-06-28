<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Bookings\PostBookingMessage;
use App\Enums\BookingMessageSenderRole;
use App\Http\Controllers\Concerns\BuildsBookingChatPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingMessageRequest;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingChatController extends Controller
{
    use BuildsBookingChatPayload;

    public function show(Request $request, Booking $booking): Response
    {
        $user = $this->user($request);
        $this->authorizeCustomer($booking, $user);

        return Inertia::render('booking/Chat', $this->bookingChatPayload(
            booking: $booking,
            viewer: $user,
            viewerRole: BookingMessageSenderRole::Customer,
            backUrl: route('customer.bookings.show', ['booking' => $booking]),
        ));
    }

    public function store(
        StoreBookingMessageRequest $request,
        Booking $booking,
        PostBookingMessage $postBookingMessage,
    ): RedirectResponse {
        $user = $this->user($request);
        $this->authorizeCustomer($booking, $user);
        $postBookingMessage->handle($booking, $user, $request->body());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message sent.')]);

        return back();
    }

    private function authorizeCustomer(Booking $booking, User $user): void
    {
        abort_unless($booking->customer_id === $user->id, 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
