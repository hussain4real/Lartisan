<?php

namespace App\Actions\Payments;

use App\Models\ArtisanProfile;
use App\Models\Wallet;

class EnsureWallet
{
    public function handle(ArtisanProfile $profile, string $currencyCode = 'NGN'): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['artisan_profile_id' => $profile->id],
            [
                'currency_code' => strtoupper($currencyCode),
                'available_balance' => 0,
                'pending_balance' => 0,
            ],
        );
    }
}
