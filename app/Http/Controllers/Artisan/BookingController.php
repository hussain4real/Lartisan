<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Bookings\AcceptBooking;
use App\Actions\Bookings\FinishBookingWork;
use App\Actions\Bookings\RejectBooking;
use App\Actions\Bookings\StartBookingWork;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function index(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);
        Gate::authorize('view', $profile);

        return Inertia::render('artisan/Bookings', [
            'bookings' => $profile->bookings()
                ->with(['customer', 'artisanService.category'])
                ->latest('id')
                ->get()
                ->map(fn (Booking $booking): array => $this->bookingPayload($booking))
                ->all(),
        ]);
    }

    public function accept(Request $request, string $currentTeam, Booking $booking, AcceptBooking $acceptBooking): RedirectResponse
    {
        $this->assertCurrentTeamBooking($request, $booking);
        $acceptBooking->handle($booking, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking accepted.')]);

        return $this->redirectToBookings($request);
    }

    public function reject(Request $request, string $currentTeam, Booking $booking, RejectBooking $rejectBooking): RedirectResponse
    {
        $this->assertCurrentTeamBooking($request, $booking);
        $rejectBooking->handle($booking, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking rejected.')]);

        return $this->redirectToBookings($request);
    }

    public function start(Request $request, string $currentTeam, Booking $booking, StartBookingWork $startBookingWork): RedirectResponse
    {
        $this->assertCurrentTeamBooking($request, $booking);
        $startBookingWork->handle($booking, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking started.')]);

        return $this->redirectToBookings($request);
    }

    public function finish(Request $request, string $currentTeam, Booking $booking, FinishBookingWork $finishBookingWork): RedirectResponse
    {
        $this->assertCurrentTeamBooking($request, $booking);
        $finishBookingWork->handle($booking, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking marked finished.')]);

        return $this->redirectToBookings($request);
    }

    private function assertCurrentTeamBooking(Request $request, Booking $booking): void
    {
        $profile = $this->artisanProfileFrom($request);
        Gate::authorize('view', $profile);
        abort_unless($booking->artisan_profile_id === $profile->id, 404);
    }

    private function redirectToBookings(Request $request): RedirectResponse
    {
        $profile = $this->artisanProfileFrom($request);

        return to_route('artisan.bookings.index', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    /**
     * @return array{id: int, status: string, customerName: string, customerPhone: string, customerEmail: string|null, scheduledAt: string|null, quotedAmountDisplay: string|null, currencyCode: string, service: array{id: int, title: string, category: string}|null, address: array<string, mixed>}
     */
    private function bookingPayload(Booking $booking): array
    {
        $service = $booking->artisanService()->first();

        return [
            'id' => $booking->id,
            'status' => $booking->status->value,
            'customerName' => $booking->customer_name,
            'customerPhone' => $booking->customer_phone,
            'customerEmail' => $booking->customer_email,
            'scheduledAt' => $booking->scheduled_at?->toISOString(),
            'quotedAmountDisplay' => $booking->quoted_amount === null ? null : number_format($booking->quoted_amount / 100, 2),
            'currencyCode' => $booking->currency_code,
            'address' => $booking->address_snapshot,
            'service' => $service === null ? null : [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category()->firstOrFail()->name,
            ],
        ];
    }
}
