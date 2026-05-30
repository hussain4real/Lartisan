<?php

namespace App\Actions\Identity;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\OtpRecord;
use App\Models\User;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyOtp
{
    public function __construct(private readonly PhoneNumberNormalizer $phoneNumberNormalizer) {}

    public function handle(
        string $phoneCountryCode,
        string $phoneNumber,
        string $code,
        OtpPurpose $purpose = OtpPurpose::PhoneVerification,
        ?User $user = null,
    ): OtpRecord {
        $phone = $this->phoneNumberNormalizer->normalize($phoneCountryCode, $phoneNumber);

        $record = OtpRecord::query()
            ->forPhonePurpose($phone['e164'], $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record instanceof OtpRecord) {
            throw ValidationException::withMessages([
                'code' => __('The verification code is invalid.'),
            ]);
        }

        if ($record->isExpired()) {
            $record->forceFill(['consumed_at' => now()])->save();

            throw ValidationException::withMessages([
                'code' => __('The verification code has expired.'),
            ]);
        }

        if (! $record->hasAttemptsRemaining()) {
            throw ValidationException::withMessages([
                'code' => __('Too many verification attempts. Request a new code.'),
            ]);
        }

        $record->increment('attempts');
        $record->refresh();

        if (! Hash::check($code, $record->code_hash)) {
            throw ValidationException::withMessages([
                'code' => __('The verification code is invalid.'),
            ]);
        }

        $targetUser = $user ?? $record->user()->first();

        $record->forceFill([
            'user_id' => $targetUser?->id,
            'verified_at' => now(),
            'consumed_at' => now(),
        ])->save();

        if ($targetUser instanceof User) {
            $targetUser->forceFill([
                'phone_country_code' => $phone['country_code'],
                'phone_number' => $phone['national'],
                'phone_e164' => $phone['e164'],
                'phone_verified_at' => now(),
                'status' => $targetUser->status === UserStatus::Suspended
                    ? UserStatus::Suspended
                    : UserStatus::Active,
            ])->save();
        }

        return $record->refresh();
    }
}
