<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\IssueOtp;
use App\Actions\Identity\VerifyOtp;
use App\Enums\OtpPurpose;
use App\Enums\PreferredChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\IssueOtpRequest;
use App\Http\Requests\Identity\VerifyOtpRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $preferredChannel = $user->preferred_channel;

        return Inertia::render('identity/PhoneVerification', [
            'phone' => [
                'countryCode' => $user->phone_country_code ?? '+234',
                'number' => $user->phone_number,
                'verified' => $user->phone_verified_at !== null,
                'verifiedAt' => $user->phone_verified_at,
                'preferredChannel' => $preferredChannel instanceof PreferredChannel ? $preferredChannel->value : PreferredChannel::Whatsapp->value,
            ],
            'channels' => collect(PreferredChannel::cases())
                ->map(fn (PreferredChannel $channel): array => [
                    'label' => str($channel->value)->replace('_', ' ')->title()->toString(),
                    'value' => $channel->value,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function issue(IssueOtpRequest $request, IssueOtp $issueOtp): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $issueOtp->handle(
            user: $user,
            phoneCountryCode: $request->phoneCountryCode(),
            phoneNumber: $request->phoneNumber(),
            purpose: OtpPurpose::PhoneVerification,
        );

        if ($request->preferredChannel() instanceof PreferredChannel) {
            $user->forceFill(['preferred_channel' => $request->preferredChannel()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification code sent.')]);

        return back();
    }

    public function verify(VerifyOtpRequest $request, VerifyOtp $verifyOtp): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $verifyOtp->handle(
            phoneCountryCode: $request->phoneCountryCode(),
            phoneNumber: $request->phoneNumber(),
            code: $request->code(),
            purpose: OtpPurpose::PhoneVerification,
            user: $user,
        );

        if ($request->preferredChannel() instanceof PreferredChannel) {
            $user->forceFill(['preferred_channel' => $request->preferredChannel()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Phone number verified.')]);

        return back();
    }
}
