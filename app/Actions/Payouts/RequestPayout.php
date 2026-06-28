<?php

namespace App\Actions\Payouts;

use App\Actions\Notifications\SendLifecycleNotification;
use App\Actions\Payments\EnsureWallet;
use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutStatus;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RequestPayout
{
    public function __construct(
        private readonly EnsureWallet $ensureWallet,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        ArtisanProfile $profile,
        PayoutAccount $payoutAccount,
        User $requester,
        int $amount,
        ?array $metadata = null,
    ): Payout {
        if ($profile->user_id !== $requester->id) {
            throw new AuthorizationException('Only the artisan owner can request a payout.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than zero.');
        }

        if ($payoutAccount->artisan_profile_id !== $profile->id || $payoutAccount->status !== PayoutAccountStatus::Verified) {
            throw new InvalidArgumentException('A verified payout account is required.');
        }

        $payout = DB::transaction(function () use ($profile, $payoutAccount, $requester, $amount, $metadata): Payout {
            $wallet = $this->ensureWallet->handle($profile);
            $wallet->refresh();

            if ($wallet->available_balance < $amount) {
                throw new InvalidArgumentException('Wallet balance is insufficient for this payout.');
            }

            return Payout::query()->create([
                'artisan_profile_id' => $profile->id,
                'payout_account_id' => $payoutAccount->id,
                'wallet_id' => $wallet->id,
                'requested_by' => $requester->id,
                'status' => PayoutStatus::Pending,
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'requested_at' => now(),
                'metadata' => $metadata,
            ]);
        }, attempts: 3);

        $this->sendLifecycleNotification->payoutStatusChanged($payout);

        return $payout->refresh();
    }
}
