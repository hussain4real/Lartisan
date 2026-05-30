<?php

namespace App\Actions\Customers;

use App\Models\CustomerProfile;
use App\Models\User;

class CreateCustomerProfile
{
    /**
     * Create or refresh the customer identity profile for a user.
     *
     * @param  array<string, mixed>|null  $preferences
     */
    public function handle(User $user, ?int $defaultAddressId = null, ?array $preferences = null): CustomerProfile
    {
        return CustomerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'default_address_id' => $defaultAddressId,
                'preferences' => $preferences,
            ],
        );
    }
}
