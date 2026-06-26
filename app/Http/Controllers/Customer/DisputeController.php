<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Disputes\OpenDispute;
use App\Enums\DisputeSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreDisputeRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function create(Request $request, Booking $booking): Response
    {
        $this->authorizeCustomer($request, $booking);

        return Inertia::render('customer/DisputeCreate', [
            'booking' => [
                'id' => $booking->id,
                'trackerCode' => $booking->tracker_code,
                'status' => $booking->status->value,
                'artisan' => [
                    'businessName' => $booking->artisanProfile()->firstOrFail()->business_name,
                ],
            ],
            'review' => $booking->review()->first()?->only(['id', 'rating', 'comment']),
            'payment' => $booking->payments()->latest('id')->first()?->only(['id', 'reference']),
        ]);
    }

    public function store(
        StoreDisputeRequest $request,
        Booking $booking,
        OpenDispute $openDispute,
    ): RedirectResponse {
        $this->authorizeCustomer($request, $booking);
        $reviewId = $request->integer('review_id') ?: null;
        $review = $reviewId === null ? null : $booking->review()->whereKey($reviewId)->firstOrFail();
        $paymentId = $request->paymentId();
        $payment = $paymentId === null ? null : $booking->payments()->whereKey($paymentId)->firstOrFail();
        $uploadedEvidence = $request->file('evidence', []);
        /** @var array<int, UploadedFile> $evidence */
        $evidence = is_array($uploadedEvidence) ? array_values($uploadedEvidence) : [$uploadedEvidence];

        $openDispute->handle(
            booking: $booking,
            actor: $this->user($request),
            subject: $request->string('subject')->trim()->toString(),
            description: $request->string('description')->trim()->toString() ?: null,
            severity: DisputeSeverity::from($request->string('severity')->toString()),
            review: $review,
            evidence: $evidence,
            payment: $payment instanceof Payment ? $payment : null,
            target: $request->target(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dispute opened.')]);

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
}
