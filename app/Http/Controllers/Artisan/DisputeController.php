<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Disputes\OpenDispute;
use App\Enums\DisputeSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\StoreDisputeRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function create(Request $request, string $currentTeam, Booking $booking): Response
    {
        $this->assertCurrentTeamBooking($request, $booking);

        return Inertia::render('artisan/DisputeCreate', [
            'booking' => [
                'id' => $booking->id,
                'trackerCode' => $booking->tracker_code,
                'status' => $booking->status->value,
                'customerName' => $booking->customer_name,
            ],
            'payment' => $booking->payments()->latest('id')->first()?->only(['id', 'reference']),
        ]);
    }

    public function store(
        StoreDisputeRequest $request,
        string $currentTeam,
        Booking $booking,
        OpenDispute $openDispute,
    ): RedirectResponse {
        $this->assertCurrentTeamBooking($request, $booking);
        $user = $request->user();
        assert($user instanceof User);
        $paymentId = $request->paymentId();
        $payment = $paymentId === null ? null : $booking->payments()->whereKey($paymentId)->firstOrFail();
        $uploadedEvidence = $request->file('evidence', []);
        /** @var array<int, UploadedFile> $evidence */
        $evidence = is_array($uploadedEvidence) ? array_values($uploadedEvidence) : [$uploadedEvidence];

        $openDispute->handle(
            booking: $booking,
            actor: $user,
            subject: $request->string('subject')->trim()->toString(),
            description: $request->string('description')->trim()->toString() ?: null,
            severity: DisputeSeverity::from($request->string('severity')->toString()),
            evidence: $evidence,
            payment: $payment instanceof Payment ? $payment : null,
            target: $request->target(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dispute opened.')]);

        return to_route('artisan.bookings.index', ['current_team' => $currentTeam]);
    }

    private function assertCurrentTeamBooking(Request $request, Booking $booking): void
    {
        $profile = $this->artisanProfileFrom($request);
        Gate::authorize('view', $profile);
        abort_unless($booking->artisan_profile_id === $profile->id, 404);
    }
}
