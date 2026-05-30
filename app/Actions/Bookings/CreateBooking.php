<?php

namespace App\Actions\Bookings;

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\BookingStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\User;
use App\Support\Bookings\CreatedBooking;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CreateBooking
{
    public function __construct(
        private readonly RecordBookingStatus $recordBookingStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $addressSnapshot
     * @param  array<int, string|UploadedFile>  $attachments
     */
    public function handle(
        ArtisanProfile $profile,
        ArtisanService $service,
        ?User $customer,
        string $customerName,
        string $customerPhone,
        ?string $customerEmail,
        array $addressSnapshot,
        ?CarbonInterface $scheduledAt = null,
        ?string $description = null,
        array $attachments = [],
    ): CreatedBooking {
        if (! $this->isBookable($profile)) {
            throw new InvalidArgumentException('This artisan is not available for bookings.');
        }

        if ($service->artisan_profile_id !== $profile->id || $service->status !== ArtisanServiceStatus::Active) {
            throw new InvalidArgumentException('The selected service is not available for this artisan.');
        }

        $trackerToken = Str::random(48);
        $booking = DB::transaction(function () use (
            $profile,
            $service,
            $customer,
            $customerName,
            $customerPhone,
            $customerEmail,
            $addressSnapshot,
            $scheduledAt,
            $description,
            $trackerToken,
        ): Booking {
            $booking = Booking::query()->create([
                'customer_id' => $customer?->id,
                'artisan_profile_id' => $profile->id,
                'artisan_service_id' => $service->id,
                'service_category_id' => $service->service_category_id,
                'status' => BookingStatus::Requested,
                'customer_name' => trim($customerName),
                'customer_phone' => trim($customerPhone),
                'customer_email' => $customerEmail === null ? null : trim($customerEmail),
                'scheduled_at' => $scheduledAt,
                'description' => $description === null ? null : trim($description),
                'quoted_amount' => $this->minorAmount($service),
                'currency_code' => strtoupper($service->currency_code),
                'address_snapshot' => $addressSnapshot,
                'country_id' => $addressSnapshot['country_id'] ?? null,
                'state_id' => $addressSnapshot['state_id'] ?? null,
                'local_government_id' => $addressSnapshot['local_government_id'] ?? null,
                'territory_id' => $addressSnapshot['territory_id'] ?? null,
                'tracker_code' => $this->trackerCode(),
                'secure_token_hash' => hash('sha256', $trackerToken),
            ]);

            $this->recordBookingStatus->handle(
                booking: $booking,
                actor: $customer,
                fromStatus: null,
                toStatus: BookingStatus::Requested,
                notes: 'booking.requested',
                metadata: ['source' => $customer instanceof User ? 'registered' : 'guest'],
            );

            return $booking;
        }, attempts: 3);

        foreach ($attachments as $attachment) {
            $booking
                ->addMedia($attachment)
                ->toMediaCollection(Booking::MEDIA_COLLECTION);
        }

        return new CreatedBooking($booking->refresh(), $trackerToken);
    }

    private function isBookable(ArtisanProfile $profile): bool
    {
        return $profile->verification_status === ArtisanVerificationStatus::Approved
            && $profile->subscription_status === ArtisanSubscriptionStatus::Active
            && $profile->availability_status !== ArtisanAvailabilityStatus::Vacation
            && $profile->is_public
            && $profile->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '>', now())
                ->exists();
    }

    private function minorAmount(ArtisanService $service): ?int
    {
        return $service->starting_price === null ? null : (int) round(((float) $service->starting_price) * 100);
    }

    private function trackerCode(): string
    {
        do {
            $code = 'BK-'.Str::upper(Str::random(10));
        } while (Booking::query()->where('tracker_code', $code)->exists());

        return $code;
    }
}
