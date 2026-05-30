<?php

namespace App\Actions\Identity;

use App\Enums\AccountClaimStatus;
use App\Enums\UserStatus;
use App\Models\AccountClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimAgentCreatedAccount
{
    public function handle(string $token, string $password, ?string $name = null): User
    {
        $claim = AccountClaim::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $claim instanceof AccountClaim) {
            throw ValidationException::withMessages([
                'token' => __('This account claim link is invalid.'),
            ]);
        }

        if ($claim->status !== AccountClaimStatus::Pending) {
            throw ValidationException::withMessages([
                'token' => __('This account claim link has already been used.'),
            ]);
        }

        if ($claim->isExpired()) {
            $claim->forceFill(['status' => AccountClaimStatus::Expired])->save();

            throw ValidationException::withMessages([
                'token' => __('This account claim link has expired.'),
            ]);
        }

        return DB::transaction(function () use ($claim, $password, $name): User {
            $user = $claim->user()->firstOrFail();
            $trimmedName = $name !== null ? trim($name) : null;

            $user->forceFill([
                'name' => $trimmedName !== '' && $trimmedName !== null ? $trimmedName : $user->name,
                'password' => $password,
                'status' => UserStatus::Active,
            ])->save();

            $claim->forceFill([
                'claimed_by' => $user->id,
                'status' => AccountClaimStatus::Claimed,
                'claimed_at' => now(),
            ])->save();

            return $user->refresh();
        });
    }
}
