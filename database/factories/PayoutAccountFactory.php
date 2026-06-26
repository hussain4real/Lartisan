<?php

namespace Database\Factories;

use App\Enums\PaymentProviderName;
use App\Enums\PayoutAccountStatus;
use App\Models\ArtisanProfile;
use App\Models\PayoutAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutAccount>
 */
class PayoutAccountFactory extends Factory
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
            'provider' => PaymentProviderName::Paystack,
            'bank_code' => '058',
            'bank_name' => 'Guaranty Trust Bank',
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'recipient_code' => null,
            'status' => PayoutAccountStatus::Pending,
            'verified_at' => null,
            'verification_checked_at' => null,
            'recipient_registered_at' => null,
            'verification_provider_status' => null,
            'verification_failure_reason' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_code' => 'RCP_'.fake()->unique()->lexify('????????'),
            'status' => PayoutAccountStatus::Verified,
            'verified_at' => now(),
            'verification_checked_at' => now(),
            'recipient_registered_at' => now(),
            'verification_provider_status' => 'active',
            'verification_failure_reason' => null,
        ]);
    }
}
