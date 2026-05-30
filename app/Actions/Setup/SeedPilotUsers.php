<?php

namespace App\Actions\Setup;

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Actions\Customers\CreateCustomerProfile;
use App\Enums\AdminProfileStatus;
use App\Enums\PlatformRole;
use App\Enums\TeamKind;
use App\Enums\TeamRole;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\ArtisanProfile;
use App\Models\Country;
use App\Models\CustomerProfile;
use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\Team;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedPilotUsers
{
    private const PASSWORD = 'password';

    private const ASSIGNMENT_STARTS_AT = '2026-01-01 00:00:00';

    public function __construct(
        private readonly SeedGeography $seedGeography,
        private readonly SeedPlatformAccess $seedPlatformAccess,
        private readonly CreateArtisanBusinessProfile $createArtisanBusinessProfile,
        private readonly UpdateArtisanBusinessLocation $updateArtisanBusinessLocation,
        private readonly CreateCustomerProfile $createCustomerProfile,
    ) {}

    /**
     * Seed the pilot operating users and their scoped demo records.
     *
     * @return array{
     *     super_admin: User,
     *     state_coordinator: User,
     *     local_government_admin: User,
     *     area_agent: User,
     *     artisan: User,
     *     customer: User,
     *     customer_profile: CustomerProfile,
     *     artisan_profile: ArtisanProfile
     * }
     */
    public function handle(): array
    {
        $this->seedGeography->handle();
        $this->seedPlatformAccess->handle();

        return DB::transaction(function (): array {
            $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
            $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
            $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
            $wuseMarket = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
            $garkiMarket = Territory::query()->where('slug', 'garki-market')->firstOrFail();

            $superAdmin = $this->upsertUser('Lartisan Super Admin', 'super.admin@lartisan.test');
            $stateCoordinator = $this->upsertUser('FCT State Coordinator', 'state.coordinator@lartisan.test');
            $localGovernmentAdmin = $this->upsertUser('AMAC LGA Admin', 'lga.admin@lartisan.test');
            $areaAgent = $this->upsertUser('Wuse Area Agent', 'area.agent@lartisan.test');
            $artisan = $this->upsertUser('Pilot Artisan Owner', 'artisan@lartisan.test');
            $customer = $this->upsertUser('Pilot Customer', 'customer@lartisan.test');

            $this->assignPlatformRole($superAdmin, PlatformRole::SuperAdmin);
            $this->assignPlatformRole($stateCoordinator, PlatformRole::StateCoordinator);
            $this->assignPlatformRole($localGovernmentAdmin, PlatformRole::LocalGovernmentAdmin);
            $this->assignPlatformRole($areaAgent, PlatformRole::AreaAgent);
            $this->assignPlatformRole($artisan, PlatformRole::Artisan);
            $this->assignPlatformRole($customer, PlatformRole::Customer);
            $customerProfile = $this->createCustomerProfile->handle($customer, preferences: [
                'preferred_channel' => 'whatsapp',
                'service_area' => 'Wuse',
            ]);

            $this->upsertAdminProfile($superAdmin, PlatformRole::SuperAdmin);
            $this->upsertAdminProfile($stateCoordinator, PlatformRole::StateCoordinator, $state, $superAdmin);
            $this->upsertAdminProfile($localGovernmentAdmin, PlatformRole::LocalGovernmentAdmin, $localGovernment, $stateCoordinator);
            $this->upsertAdminProfile($areaAgent, PlatformRole::AreaAgent, $localGovernment, $localGovernmentAdmin);

            $this->upsertAreaAssignment($areaAgent, $wuseMarket, $localGovernmentAdmin);
            $this->upsertAreaAssignment($areaAgent, $garkiMarket, $localGovernmentAdmin);

            $artisanProfile = $this->upsertArtisanProfile($artisan, $areaAgent);
            $artisanProfile = $this->updateArtisanBusinessLocation->handle(
                $artisanProfile,
                $country,
                $state,
                $localGovernment,
                $wuseMarket,
            );

            return [
                'super_admin' => $superAdmin->refresh(),
                'state_coordinator' => $stateCoordinator->refresh(),
                'local_government_admin' => $localGovernmentAdmin->refresh(),
                'area_agent' => $areaAgent->refresh(),
                'artisan' => $artisan->refresh(),
                'customer' => $customer->refresh(),
                'customer_profile' => $customerProfile->refresh(),
                'artisan_profile' => $artisanProfile->refresh(),
            ];
        });
    }

    private function upsertUser(string $name, string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'remember_token' => Str::random(10),
            ]);
            $user->save();
        } else {
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => now(),
            ])->save();
        }

        $this->ensurePersonalTeam($user);

        return $user->refresh();
    }

    private function ensurePersonalTeam(User $user): void
    {
        if ($user->personalTeam() instanceof Team) {
            return;
        }

        $team = Team::query()->create([
            'name' => $user->name."'s Team",
            'kind' => TeamKind::Personal,
            'is_personal' => true,
        ]);

        $team->members()->syncWithoutDetaching([
            $user->id => ['role' => TeamRole::Owner->value],
        ]);

        if ($user->current_team_id === null) {
            $user->switchTeam($team);
        }
    }

    private function assignPlatformRole(User $user, PlatformRole $role): void
    {
        $user->syncRoles([$role->value]);
    }

    private function upsertAdminProfile(
        User $user,
        PlatformRole $role,
        State|LocalGovernment|Territory|null $scope = null,
        ?User $appointedBy = null,
    ): AdminProfile {
        return AdminProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $role,
                'scope_type' => $scope?->getMorphClass(),
                'scope_id' => $scope?->id,
                'status' => AdminProfileStatus::Active,
                'appointed_by' => $appointedBy?->id,
                'appointed_at' => self::ASSIGNMENT_STARTS_AT,
            ],
        );
    }

    private function upsertAreaAssignment(User $agent, Territory $territory, User $assignedBy): AreaAgentAssignment
    {
        return AreaAgentAssignment::query()->updateOrCreate(
            [
                'user_id' => $agent->id,
                'territory_id' => $territory->id,
                'starts_at' => self::ASSIGNMENT_STARTS_AT,
            ],
            [
                'ends_at' => null,
                'assigned_by' => $assignedBy->id,
                'reason' => 'Pilot market coverage',
            ],
        );
    }

    private function upsertArtisanProfile(User $artisan, User $areaAgent): ArtisanProfile
    {
        $artisanProfile = $artisan->artisanProfiles()
            ->where('business_name', 'Wuse Sparks Electrical')
            ->first();

        if ($artisanProfile instanceof ArtisanProfile) {
            $artisanProfile->update([
                'onboarded_by_agent_id' => $areaAgent->id,
                'internal_notes' => 'Pilot artisan seeded for onboarding and operations demos.',
            ]);

            return $artisanProfile->refresh();
        }

        return $this->createArtisanBusinessProfile->handle(
            owner: $artisan,
            businessName: 'Wuse Sparks Electrical',
            onboardedByAgent: $areaAgent,
            internalNotes: 'Pilot artisan seeded for onboarding and operations demos.',
        );
    }
}
