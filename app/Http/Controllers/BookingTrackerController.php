<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\ConfirmBookingCompletion;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingTrackerController extends Controller
{
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

    private function bookingFromTracker(Request $request, string $trackerCode): Booking
    {
        $booking = Booking::query()
            ->with(['artisanProfile', 'artisanService.category', 'payments', 'statusHistories.actor'])
            ->where('tracker_code', $trackerCode)
            ->firstOrFail();

        abort_unless(hash_equals($booking->secure_token_hash, hash('sha256', $this->trackerToken($request))), 403);

        return $booking;
    }

    private function trackerToken(Request $request): string
    {
        $token = $request->input('token', $request->query('token', ''));

        return is_scalar($token) ? (string) $token : '';
    }

    /**
     * @return array{id: int, trackerCode: string, status: string, customerName: string, customerPhone: string, customerEmail: string|null, scheduledAt: string|null, description: string|null, quotedAmount: int|null, quotedAmountDisplay: string|null, currencyCode: string, address: array<string, mixed>, artisan: array{id: int, businessName: string}, service: array{id: int, title: string, category: string}|null, canPay: bool, payment: array{id: int, status: string, reference: string, amountDisplay: string, commissionDisplay: string|null, providerFeeDisplay: string|null, netAmountDisplay: string|null, checkoutUrl: string|null}|null, histories: array<int, array{id: int, fromStatus: string|null, toStatus: string, notes: string|null, actorName: string|null, createdAt: string|null}>}
     */
    private function bookingPayload(Booking $booking): array
    {
        $service = $booking->artisanService()->first();
        $payment = $booking->payments->sortByDesc('id')->first();

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
            'canPay' => $booking->status->value === 'accepted' && $booking->quoted_amount !== null && $booking->quoted_amount > 0,
            'payment' => $payment instanceof Payment ? $this->paymentPayload($payment) : null,
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
