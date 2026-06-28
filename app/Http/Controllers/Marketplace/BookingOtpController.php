<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Identity\IssueOtp;
use App\Enums\OtpPurpose;
use App\Enums\PreferredChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\IssueBookingOtpRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BookingOtpController extends Controller
{
    public function __invoke(IssueBookingOtpRequest $request, IssueOtp $issueOtp): RedirectResponse
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        $issueOtp->handle(
            user: $user,
            phoneCountryCode: $request->phoneCountryCode(),
            phoneNumber: $request->customerPhone(),
            purpose: OtpPurpose::BookingGuest,
        );

        if ($user instanceof User && $request->preferredChannel() instanceof PreferredChannel) {
            $user->forceFill(['preferred_channel' => $request->preferredChannel()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking verification code sent.')]);

        return back();
    }
}
