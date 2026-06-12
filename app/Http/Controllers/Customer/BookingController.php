<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Review;
use App\Models\ServiceCategory;
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
                ->with(['artisanProfile', 'artisanService.category', 'review', 'disputes'])
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
            'booking' => $this->bookingCardPayload($booking->load(['artisanProfile', 'artisanService.category', 'review', 'disputes'])),
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
     * @return array{id: int, status: string, customerName: string, scheduledAt: string|null, quotedAmountDisplay: string|null, currencyCode: string, artisan: array{id: int, businessName: string}, service: array{id: int, title: string, category: string}|null, trackerCode: string, canReview: bool, review: array{id: int, rating: int, comment: string|null, status: string}|null, disputes: array<int, array{id: int, status: string, severity: string, subject: string, openedAt: string|null}>}
     */
    private function bookingCardPayload(Booking $booking): array
    {
        $artisanProfile = $booking->artisanProfile;
        $service = $booking->artisanService;
        $review = $booking->review;

        assert($artisanProfile instanceof ArtisanProfile);

        return [
            'id' => $booking->id,
            'status' => $booking->status->value,
            'customerName' => $booking->customer_name,
            'scheduledAt' => $booking->scheduled_at?->toISOString(),
            'quotedAmountDisplay' => $booking->quoted_amount === null ? null : number_format($booking->quoted_amount / 100, 2),
            'currencyCode' => $booking->currency_code,
            'trackerCode' => $booking->tracker_code,
            'artisan' => [
                'id' => $artisanProfile->id,
                'businessName' => $artisanProfile->business_name,
            ],
            'service' => $service instanceof ArtisanService ? $this->servicePayload($service) : null,
            'canReview' => $booking->status->value === 'confirmed' && $booking->wallet_released_at !== null && ! $review instanceof Review,
            'review' => $review instanceof Review ? [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status->value,
            ] : null,
            'disputes' => $booking->disputes
                ->sortByDesc('id')
                ->values()
                ->map(fn (Dispute $dispute): array => [
                    'id' => $dispute->id,
                    'status' => $dispute->status->value,
                    'severity' => $dispute->severity->value,
                    'subject' => $dispute->subject,
                    'openedAt' => $dispute->opened_at->toISOString(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, title: string, category: string}
     */
    private function servicePayload(ArtisanService $service): array
    {
        $category = $service->category;
        assert($category instanceof ServiceCategory);

        return [
            'id' => $service->id,
            'title' => $service->title,
            'category' => $category->name,
        ];
    }
}
