<?php

namespace App\Actions\Identity;

use App\Enums\OtpPurpose;
use App\Models\OtpRecord;
use App\Models\User;
use App\Support\IssuedOtp;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class IssueOtp
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function __construct(private readonly PhoneNumberNormalizer $phoneNumberNormalizer) {}

    public function handle(
        ?User $user,
        string $phoneCountryCode,
        string $phoneNumber,
        OtpPurpose $purpose = OtpPurpose::PhoneVerification,
        ?string $plainCode = null,
    ): IssuedOtp {
        $phone = $this->phoneNumberNormalizer->normalize($phoneCountryCode, $phoneNumber);
        $rateLimitKey = $this->rateLimitKey($phone['e164'], $purpose);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone_number' => __('Too many OTP requests. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($rateLimitKey),
                ]),
            ]);
        }

        RateLimiter::hit($rateLimitKey, self::DECAY_SECONDS);

        $plainCode ??= str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = OtpRecord::query()->create([
            'user_id' => $user?->id,
            'phone_country_code' => $phone['country_code'],
            'phone_number' => $phone['national'],
            'phone_e164' => $phone['e164'],
            'email' => $user?->email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($plainCode),
            'attempts' => 0,
            'max_attempts' => self::MAX_ATTEMPTS,
            'expires_at' => now()->addMinutes(10),
            'last_sent_at' => now(),
        ]);

        return new IssuedOtp($record, $plainCode);
    }

    public function rateLimitKey(string $phoneE164, OtpPurpose $purpose): string
    {
        return "otp:{$purpose->value}:{$phoneE164}";
    }
}
