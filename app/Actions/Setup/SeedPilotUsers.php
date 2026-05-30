<?php

namespace App\Actions\Setup;

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Artisans\CreateArtisanService;
use App\Actions\Artisans\SubmitKyc;
use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Actions\Customers\CreateCustomerProfile;
use App\Enums\AdminProfileStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformRole;
use App\Enums\PreferredChannel;
use App\Enums\ReasonCodeCategory;
use App\Enums\TeamKind;
use App\Enums\TeamRole;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Country;
use App\Models\CustomerProfile;
use App\Models\KycSubmission;
use App\Models\LocalGovernment;
use App\Models\ReasonCode;
use App\Models\ServiceCategory;
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
        private readonly SeedReasonCodes $seedReasonCodes,
        private readonly CreateArtisanBusinessProfile $createArtisanBusinessProfile,
        private readonly UpdateArtisanBusinessLocation $updateArtisanBusinessLocation,
        private readonly CreateCustomerProfile $createCustomerProfile,
        private readonly CreateArtisanService $createArtisanService,
        private readonly SubmitKyc $submitKyc,
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
     *     customer_address: Address,
     *     artisan_profile: ArtisanProfile,
     *     artisan_service: ArtisanService,
     *     kyc_submission: KycSubmission
     * }
     */
    public function handle(): array
    {
        $this->seedGeography->handle();
        $this->seedPlatformAccess->handle();
        $this->seedReasonCodes->handle();

        return DB::transaction(function (): array {
            $country = Country::query()->where('iso_code', 'NG')->firstOrFail();
            $state = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
            $localGovernment = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
            $wuseMarket = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
            $garkiMarket = Territory::query()->where('slug', 'garki-market')->firstOrFail();

            $superAdmin = $this->upsertUser('Lartisan Super Admin', 'super.admin@lartisan.test', '8000000001', PreferredChannel::Email);
            $stateCoordinator = $this->upsertUser('FCT State Coordinator', 'state.coordinator@lartisan.test', '8000000002', PreferredChannel::Whatsapp);
            $localGovernmentAdmin = $this->upsertUser('AMAC LGA Admin', 'lga.admin@lartisan.test', '8000000003', PreferredChannel::Whatsapp);
            $areaAgent = $this->upsertUser('Wuse Area Agent', 'area.agent@lartisan.test', '8000000004', PreferredChannel::Sms);
            $artisan = $this->upsertUser('Pilot Artisan Owner', 'artisan@lartisan.test', '8031234567', PreferredChannel::Whatsapp);
            $customer = $this->upsertUser('Pilot Customer', 'customer@lartisan.test', '8057654321', PreferredChannel::Whatsapp);

            $this->assignPlatformRole($superAdmin, PlatformRole::SuperAdmin);
            $this->assignPlatformRole($stateCoordinator, PlatformRole::StateCoordinator);
            $this->assignPlatformRole($localGovernmentAdmin, PlatformRole::LocalGovernmentAdmin);
            $this->assignPlatformRole($areaAgent, PlatformRole::AreaAgent);
            $this->assignPlatformRole($artisan, PlatformRole::Artisan);
            $this->assignPlatformRole($customer, PlatformRole::Customer);
            $customerAddress = $this->upsertCustomerAddress($customer, $country, $state, $localGovernment, $wuseMarket);
            $customerProfile = $this->createCustomerProfile->handle($customer, $customerAddress->id, preferences: [
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
            $categories = $this->upsertServiceCategories();
            $artisanService = $this->upsertPilotService($artisanProfile, $categories['electrical']);
            $kycSubmission = $this->upsertPilotKyc($artisanProfile, $artisan);

            return [
                'super_admin' => $superAdmin->refresh(),
                'state_coordinator' => $stateCoordinator->refresh(),
                'local_government_admin' => $localGovernmentAdmin->refresh(),
                'area_agent' => $areaAgent->refresh(),
                'artisan' => $artisan->refresh(),
                'customer' => $customer->refresh(),
                'customer_profile' => $customerProfile->refresh(),
                'customer_address' => $customerAddress->refresh(),
                'artisan_profile' => $artisanProfile->refresh(),
                'artisan_service' => $artisanService->refresh(),
                'kyc_submission' => $kycSubmission->refresh(),
            ];
        });
    }

    private function upsertUser(string $name, string $email, string $phoneNumber, PreferredChannel $preferredChannel): User
    {
        $phoneE164 = '+234'.$phoneNumber;
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'phone_country_code' => '+234',
                'phone_number' => $phoneNumber,
                'phone_e164' => $phoneE164,
                'phone_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'preferred_channel' => $preferredChannel,
                'remember_token' => Str::random(10),
                'status' => UserStatus::Active,
            ]);
            $user->save();
        } else {
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => now(),
                'phone_country_code' => '+234',
                'phone_number' => $phoneNumber,
                'phone_e164' => $phoneE164,
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'preferred_channel' => $preferredChannel,
                'status' => UserStatus::Active,
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
        $reasonCode = ReasonCode::query()
            ->forCategory(ReasonCodeCategory::TerritoryAssignment)
            ->where('code', 'coverage-balancing')
            ->first();

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
                'reason_code_id' => $reasonCode?->id,
            ],
        );
    }

    private function upsertCustomerAddress(
        User $customer,
        Country $country,
        State $state,
        LocalGovernment $localGovernment,
        Territory $territory,
    ): Address {
        return Address::query()->updateOrCreate(
            [
                'user_id' => $customer->id,
                'label' => 'Home',
            ],
            [
                'contact_name' => $customer->name,
                'phone' => $customer->phone_e164,
                'country_id' => $country->id,
                'state_id' => $state->id,
                'local_government_id' => $localGovernment->id,
                'territory_id' => $territory->id,
                'line_1' => 'Plot 12 Wuse Market Road',
                'line_2' => null,
                'landmark' => 'Near Wuse Market',
                'latitude' => '9.0764780',
                'longitude' => '7.4686590',
                'is_default' => true,
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

    /**
     * @return array{electrical: ServiceCategory, plumbing: ServiceCategory, carpentry: ServiceCategory}
     */
    private function upsertServiceCategories(): array
    {
        return [
            'electrical' => ServiceCategory::query()->updateOrCreate(
                ['slug' => 'electrical'],
                [
                    'name' => 'Electrical',
                    'description' => 'Electrical installation, diagnostics, and repairs.',
                    'active' => true,
                    'sort_order' => 10,
                ],
            ),
            'plumbing' => ServiceCategory::query()->updateOrCreate(
                ['slug' => 'plumbing'],
                [
                    'name' => 'Plumbing',
                    'description' => 'Plumbing maintenance and water system repairs.',
                    'active' => true,
                    'sort_order' => 20,
                ],
            ),
            'carpentry' => ServiceCategory::query()->updateOrCreate(
                ['slug' => 'carpentry'],
                [
                    'name' => 'Carpentry',
                    'description' => 'Furniture, fittings, and woodwork services.',
                    'active' => true,
                    'sort_order' => 30,
                ],
            ),
        ];
    }

    private function upsertPilotService(ArtisanProfile $artisanProfile, ServiceCategory $category): ArtisanService
    {
        $service = $artisanProfile->services()
            ->where('title', 'Residential wiring diagnostics')
            ->first();

        if ($service instanceof ArtisanService) {
            $service->update([
                'service_category_id' => $category->id,
                'description' => 'Fault finding, safety checks, and small wiring repairs for homes and shops.',
                'starting_price' => '15000.00',
                'currency_code' => 'NGN',
                'status' => ArtisanServiceStatus::Active,
            ]);

            return $service->refresh();
        }

        return $this->createArtisanService->handle(
            profile: $artisanProfile,
            category: $category,
            title: 'Residential wiring diagnostics',
            description: 'Fault finding, safety checks, and small wiring repairs for homes and shops.',
            startingPrice: '15000.00',
            currencyCode: 'NGN',
            status: ArtisanServiceStatus::Active,
        );
    }

    private function upsertPilotKyc(ArtisanProfile $artisanProfile, User $artisan): KycSubmission
    {
        $submission = $artisanProfile->kycSubmissions()->latest('id')->first();

        if ($submission instanceof KycSubmission
            && ! in_array($submission->status, [
                ArtisanVerificationStatus::Draft,
                ArtisanVerificationStatus::Returned,
                ArtisanVerificationStatus::Rejected,
            ], true)) {
            return $submission;
        }

        if (! $submission instanceof KycSubmission) {
            $artisanProfile->kycSubmissions()->create([
                'status' => ArtisanVerificationStatus::Draft,
            ]);
        }

        return $this->submitKyc->handle(
            profile: $artisanProfile,
            actor: $artisan,
            notes: 'Pilot KYC submission seeded for verification workflow demos.',
        );
    }
}
