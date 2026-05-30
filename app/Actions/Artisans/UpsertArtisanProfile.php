<?php

namespace App\Actions\Artisans;

use App\Enums\ArtisanAvailabilityStatus;
use App\Models\ArtisanProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpsertArtisanProfile
{
    public function __construct(
        private readonly RecordStatusHistory $recordStatusHistory,
    ) {}

    public function handle(
        ArtisanProfile $profile,
        User $actor,
        string $businessName,
        ?string $publicSummary = null,
        ?int $yearsExperience = null,
        ?int $serviceRadiusKm = null,
        ?string $publicPhone = null,
        ?string $publicEmail = null,
        ArtisanAvailabilityStatus $availabilityStatus = ArtisanAvailabilityStatus::Offline,
        bool $isPublic = false,
    ): ArtisanProfile {
        $businessName = trim($businessName);

        if ($businessName === '') {
            throw new InvalidArgumentException('The artisan business name is required.');
        }

        return DB::transaction(function () use ($profile, $actor, $businessName, $publicSummary, $yearsExperience, $serviceRadiusKm, $publicPhone, $publicEmail, $availabilityStatus, $isPublic): ArtisanProfile {
            $previousAvailabilityStatus = $profile->availability_status;

            $profile->update([
                'business_name' => $businessName,
                'public_summary' => $this->blankToNull($publicSummary),
                'years_experience' => $yearsExperience,
                'service_radius_km' => $serviceRadiusKm,
                'public_phone' => $this->blankToNull($publicPhone),
                'public_email' => $this->blankToNull($publicEmail),
                'availability_status' => $availabilityStatus,
                'is_public' => $isPublic,
            ]);

            if ($previousAvailabilityStatus !== $availabilityStatus) {
                $this->recordStatusHistory->handle(
                    statusable: $profile,
                    actor: $actor,
                    fromStatus: $previousAvailabilityStatus->value,
                    toStatus: $availabilityStatus->value,
                    reason: 'artisan.profile.availability_updated',
                );
            }

            return $profile->refresh();
        });
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
