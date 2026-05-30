<?php

namespace App\Actions\Artisans;

use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformRole;
use App\Enums\TeamKind;
use App\Enums\TeamRole;
use App\Models\ArtisanProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateArtisanBusinessProfile
{
    /**
     * Create a non-personal team workspace and marketplace profile for an artisan business.
     */
    public function handle(
        User $owner,
        string $businessName,
        ?User $onboardedByAgent = null,
        ?string $internalNotes = null,
    ): ArtisanProfile {
        $businessName = trim($businessName);

        if ($businessName === '') {
            throw new InvalidArgumentException('The artisan business name is required.');
        }

        return DB::transaction(function () use ($owner, $businessName, $onboardedByAgent, $internalNotes): ArtisanProfile {
            $team = Team::query()->create([
                'name' => $businessName,
                'kind' => TeamKind::ArtisanBusiness,
                'is_personal' => false,
            ]);

            $team->members()->attach($owner, [
                'role' => TeamRole::Owner->value,
            ]);

            $profile = ArtisanProfile::query()->create([
                'team_id' => $team->id,
                'user_id' => $owner->id,
                'business_name' => $businessName,
                'verification_status' => ArtisanVerificationStatus::Draft,
                'subscription_status' => ArtisanSubscriptionStatus::Trial,
                'availability_status' => ArtisanAvailabilityStatus::Offline,
                'onboarded_by_agent_id' => $onboardedByAgent?->id,
                'internal_notes' => $internalNotes,
            ]);

            $owner->assignRole(PlatformRole::Artisan->value);
            $owner->switchTeam($team);

            return $profile->refresh();
        });
    }
}
