<?php

namespace App\Actions\Bookings;

use App\Actions\Identity\VerifyOtp;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Validation\ValidationException;

class EnsureBookingOtpVerified
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly VerifyOtp $verifyOtp,
    ) {}

    /**
     * @return array{country_code: string, national: string, e164: string}
     */
    public function handle(
        ?User $customer,
        string $phoneCountryCode,
        string $phoneNumber,
        ?string $code,
    ): array {
        $phone = $this->phoneNumberNormalizer->normalize($phoneCountryCode, $phoneNumber);

        if ($customer instanceof User
            && $customer->phone_verified_at !== null
            && $customer->phone_e164 === $phone['e164']) {
            return $phone;
        }

        if ($code === null || trim($code) === '') {
            throw ValidationException::withMessages([
                'otp_code' => __('Enter the booking verification code.'),
            ]);
        }

        try {
            $this->verifyOtp->handle(
                phoneCountryCode: $phone['country_code'],
                phoneNumber: $phone['national'],
                code: $code,
                purpose: OtpPurpose::BookingGuest,
                user: $customer,
            );
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            $codeMessages = $messages['code'] ?? [];
            $message = __('The verification code is invalid.');

            if (is_array($codeMessages) && isset($codeMessages[0]) && is_string($codeMessages[0])) {
                $message = $codeMessages[0];
            }

            throw ValidationException::withMessages([
                'otp_code' => $message,
            ]);
        }

        return $phone;
    }
}
