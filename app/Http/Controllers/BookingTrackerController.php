<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Enums\BookingStatus;
use App\Http\Controllers\Concerns\ResolvesBookingTracker;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingTrackerController extends Controller
{
    use ResolvesBookingTracker;

    public function show(Request $request, string $trackerCode): Response
    {
        $booking = $this->bookingFromTracker($request, $trackerCode);

        return Inertia::render('marketplace/Tracker', [
            'booking' => $this->bookingPayload($booking),
            'token' => $this->trackerToken($request),
        ]);
    }

    public function confirm(
        Request $request,
        string $trackerCode,
        ConfirmBookingCompletion $confirmBookingCompletion,
    ): RedirectResponse {
        $booking = $this->bookingFromTracker($request, $trackerCode);
        $confirmBookingCompletion->handle($booking, trackerToken: $this->trackerToken($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking completion confirmed.')]);

        return to_route('booking-tracker.show', [
            'trackerCode' => $trackerCode,
            'token' => $this->trackerToken($request),
        ]);
    }

    /**
     * @return array{id: int, trackerCode: string, status: string, customerName: string, customerPhone: string, customerEmail: string|null, scheduledAt: string|null, description: string|null, quotedAmount: int|null, quotedAmountDisplay: string|null, currencyCode: string, address: array<string, mixed>, artisan: array{id: int, businessName: string}, service: array{id: int, title: string, category: string}|null, canPay: bool, canUpgrade: bool, canReview: bool, canDispute: bool, payment: array{id: int, status: string, reference: string, amountDisplay: string, commissionDisplay: string|null, providerFeeDisplay: string|null, netAmountDisplay: string|null, checkoutUrl: string|null}|null, review: array{id: int, rating: int, comment: string|null, status: string}|null, disputes: array<int, array{id: int, status: string, severity: string, subject: string, openedAt: string|null}>, histories: array<int, array{id: int, fromStatus: string|null, toStatus: string, notes: string|null, actorName: string|null, createdAt: string|null}>}
     */
    private function bookingPayload(Booking $booking): array
    {
        $service = $booking->artisanService()->first();
        $payment = $booking->payments->sortByDesc('id')->first();
        $review = $booking->review;

        return [
            'id' => $booking->id,
            'trackerCode' => $booking->tracker_code,
            'status' => $booking->status->value,
            'customerName' => $booking->customer_name,
            'customerPhone' => $booking->customer_phone,
            'customerEmail' => $booking->customer_email,
            'scheduledAt' => $booking->scheduled_at?->toISOString(),
            'description' => $booking->description,
            'quotedAmount' => $booking->quoted_amount,
            'quotedAmountDisplay' => $booking->quoted_amount === null ? null : number_format($booking->quoted_amount / 100, 2),
            'currencyCode' => $booking->currency_code,
            'address' => $booking->address_snapshot,
            'artisan' => [
                'id' => $booking->artisanProfile()->firstOrFail()->id,
                'businessName' => $booking->artisanProfile()->firstOrFail()->business_name,
            ],
            'service' => $service === null ? null : [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category()->firstOrFail()->name,
            ],
            'canPay' => $booking->status === BookingStatus::Accepted && $booking->quoted_amount !== null && $booking->quoted_amount > 0,
            'canUpgrade' => $booking->customer_id === null,
            'canReview' => $booking->customer_id === null
                && $booking->status === BookingStatus::Settled
                && $booking->wallet_released_at !== null
                && ! $review instanceof Review,
            'canDispute' => $booking->customer_id === null
                && ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Rejected], true),
            'payment' => $payment instanceof Payment ? $this->paymentPayload($payment) : null,
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
            'histories' => $booking->statusHistories
                ->map(fn (BookingStatusHistory $history): array => [
                    'id' => $history->id,
                    'fromStatus' => $history->from_status?->value,
                    'toStatus' => $history->to_status->value,
                    'notes' => $history->notes,
                    'actorName' => $history->actor?->name,
                    'createdAt' => $history->created_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, status: string, reference: string, amountDisplay: string, commissionDisplay: string|null, providerFeeDisplay: string|null, netAmountDisplay: string|null, checkoutUrl: string|null}
     */
    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'reference' => $payment->reference,
            'amountDisplay' => number_format($payment->amount / 100, 2),
            'commissionDisplay' => $payment->commission_amount === null ? null : number_format($payment->commission_amount / 100, 2),
            'providerFeeDisplay' => $payment->provider_fee_amount === null ? null : number_format($payment->provider_fee_amount / 100, 2),
            'netAmountDisplay' => $payment->net_amount === null ? null : number_format($payment->net_amount / 100, 2),
            'checkoutUrl' => $payment->checkout_url,
        ];
    }
}
