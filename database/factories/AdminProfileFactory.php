<?php

namespace Database\Factories;

use App\Enums\AdminProfileStatus;
use App\Enums\PlatformRole;
use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminProfile>
 */
class AdminProfileFactory extends Factory
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
            'role' => PlatformRole::StateCoordinator,
            'scope_type' => null,
            'scope_id' => null,
            'status' => AdminProfileStatus::Active,
            'appointed_by' => null,
            'appointed_at' => now(),
        ];
    }
}
