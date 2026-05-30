<?php

namespace Database\Factories;

use App\Models\ArtisanProfile;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artisan_profile_id' => ArtisanProfile::factory(),
            'currency_code' => 'NGN',
            'available_balance' => 0,
            'pending_balance' => 0,
        ];
    }
}
