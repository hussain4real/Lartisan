<?php

namespace Database\Factories;

use App\Models\ArtisanProfile;
use App\Models\CustomerFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerFavorite>
 */
class CustomerFavoriteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'artisan_profile_id' => ArtisanProfile::factory(),
        ];
    }
}
