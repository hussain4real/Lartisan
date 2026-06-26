<?php

namespace App\Actions\Bookings;

use App\Actions\Customers\CreateCustomerProfile;
use App\Actions\Teams\CreateTeam;
use App\Enums\PreferredChannel;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Country;
use App\Models\User;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpgradeGuestBookingAccount
{
    public function __construct(
        private readonly CreateTeam $createTeam,
        private readonly CreateCustomerProfile $createCustomerProfile,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    public function handle(
        Booking $booking,
        string $trackerToken,
        string $name,
        string $email,
        string $password,
    ): User {
        return DB::transaction(function () use ($booking, $trackerToken, $name, $email, $password): User {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! hash_equals($booking->secure_token_hash, hash('sha256', $trackerToken))) {
                throw new AuthorizationException('The booking tracker token is invalid.');
            }

            if ($booking->customer_id !== null) {
                throw new InvalidArgumentException('This booking is already attached to a customer account.');
            }

            $countryCode = $this->countryCodeFor($booking);
            $phone = $this->phoneNumberNormalizer->normalize($countryCode, $booking->customer_phone);
            $user = User::query()->create([
                'name' => trim($name),
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
                'phone_country_code' => $phone['country_code'],
                'phone_number' => $phone['national'],
                'phone_e164' => $phone['e164'],
                'phone_verified_at' => now(),
                'status' => UserStatus::Active,
                'preferred_channel' => PreferredChannel::Email,
            ]);

            $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

            $address = $this->createAddressFromBooking($booking, $user);
            $this->createCustomerProfile->handle(
                user: $user,
                defaultAddressId: $address?->id,
                preferences: [
                    'preferred_channel' => PreferredChannel::Email->value,
                    'schedule_window' => 'flexible',
                    'default_notes' => null,
                ],
            );

            $booking->forceFill([
                'customer_id' => $user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
            ])->save();

            return $user->refresh();
        }, attempts: 3);
    }

    private function createAddressFromBooking(Booking $booking, User $user): ?Address
    {
        $snapshot = $booking->address_snapshot;
        $stateId = $snapshot['state_id'] ?? null;
        $localGovernmentId = $snapshot['local_government_id'] ?? null;
        $line = $snapshot['line_1'] ?? null;

        if (! is_int($stateId) || ! is_int($localGovernmentId) || ! is_string($line) || trim($line) === '') {
            return null;
        }

        return Address::query()->create([
            'user_id' => $user->id,
            'label' => is_string($snapshot['label'] ?? null) ? $snapshot['label'] : 'Service address',
            'contact_name' => $booking->customer_name,
            'phone' => $booking->customer_phone,
            'country_id' => is_int($snapshot['country_id'] ?? null) ? $snapshot['country_id'] : null,
            'state_id' => $stateId,
            'local_government_id' => $localGovernmentId,
            'territory_id' => is_int($snapshot['territory_id'] ?? null) ? $snapshot['territory_id'] : null,
            'line_1' => $line,
            'line_2' => is_string($snapshot['line_2'] ?? null) ? $snapshot['line_2'] : null,
            'landmark' => is_string($snapshot['landmark'] ?? null) ? $snapshot['landmark'] : null,
            'is_default' => true,
        ]);
    }

    private function countryCodeFor(Booking $booking): string
    {
        if ($booking->country_id === null) {
            return '+234';
        }

        $countryCode = Country::query()
            ->whereKey($booking->country_id)
            ->value('phone_country_code');

        return is_string($countryCode) && trim($countryCode) !== '' ? $countryCode : '+234';
    }
}
