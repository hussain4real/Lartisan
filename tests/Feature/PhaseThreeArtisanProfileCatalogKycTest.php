<?php

use App\Actions\Artisans\AttachKycMedia;
use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Artisans\CreateArtisanService;
use App\Actions\Artisans\RecordFieldVisit;
use App\Actions\Artisans\RecordStatusHistory;
use App\Actions\Artisans\SubmitKyc;
use App\Actions\Artisans\UpdateArtisanBusinessLocation;
use App\Actions\Artisans\UpsertArtisanProfile;
use App\Actions\Setup\SeedGeography;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\FieldVisitStatus;
use App\Enums\KycRiskLevel;
use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Http\Controllers\Artisan\DashboardController;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\FieldVisit;
use App\Models\KycSubmission;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\StatusHistory;
use App\Models\Territory;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\PilotUserSeeder;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(GeographySeeder::class);
    $this->seed(PlatformAccessSeeder::class);
});

/**
 * @return array{localGovernment: LocalGovernment, territory: Territory}
 */
function phaseThreeGeography(): array
{
    app(SeedGeography::class)->handle();

    return [
        'localGovernment' => LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail(),
        'territory' => Territory::query()->where('slug', 'wuse-market')->firstOrFail(),
    ];
}

/**
 * @return array{profile: ArtisanProfile, owner: User, agent: User, category: ServiceCategory}
 */
function phaseThreeBusiness(): array
{
    $geography = phaseThreeGeography();
    $owner = User::factory()->create();
    $agent = User::factory()->create();
    $agent->assignRole(PlatformRole::AreaAgent->value);
    AdminProfile::factory()->create([
        'user_id' => $agent->id,
        'role' => PlatformRole::AreaAgent,
        'scope_type' => $geography['localGovernment']->getMorphClass(),
        'scope_id' => $geography['localGovernment']->id,
    ]);
    AreaAgentAssignment::factory()->create([
        'user_id' => $agent->id,
        'territory_id' => $geography['territory']->id,
        'assigned_by' => $owner->id,
    ]);

    $profile = app(CreateArtisanBusinessProfile::class)->handle(
        owner: $owner,
        businessName: 'Phase Three Electrical',
        onboardedByAgent: $agent,
    );
    $country = $geography['localGovernment']->state()->firstOrFail()->country()->firstOrFail();
    $state = $geography['localGovernment']->state()->firstOrFail();
    $profile = app(UpdateArtisanBusinessLocation::class)->handle(
        profile: $profile,
        country: $country,
        state: $state,
        localGovernment: $geography['localGovernment'],
        territory: $geography['territory'],
    );
    $category = ServiceCategory::factory()->create([
        'name' => 'Electrical',
        'slug' => 'electrical-phase-three',
    ]);

    return [
        'profile' => $profile,
        'owner' => $owner->refresh(),
        'agent' => $agent->refresh(),
        'category' => $category,
    ];
}

test('phase three models expose catalog kyc visit media and status relationships', function () {
    Storage::fake('local');
    Storage::fake('public');

    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $agent = $business['agent'];
    $category = $business['category'];
    $childCategory = ServiceCategory::factory()->create([
        'parent_id' => $category->id,
        'name' => 'Emergency Electrical',
        'slug' => 'emergency-electrical',
    ]);
    $reviewer = User::factory()->create();

    $service = ArtisanService::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => $childCategory->id,
        'starting_price' => '25000.00',
    ]);
    $submission = KycSubmission::factory()->submitted()->reviewed()->create([
        'artisan_profile_id' => $profile->id,
        'reviewed_by' => $reviewer->id,
        'risk_level' => KycRiskLevel::Medium,
    ]);
    $visit = FieldVisit::factory()->completed()->create([
        'artisan_profile_id' => $profile->id,
        'kyc_submission_id' => $submission->id,
        'area_agent_id' => $agent->id,
        'territory_id' => $profile->territory_id,
    ]);
    $history = app(RecordStatusHistory::class)->handle(
        statusable: $profile,
        actor: $owner,
        fromStatus: ArtisanVerificationStatus::Draft->value,
        toStatus: ArtisanVerificationStatus::Submitted->value,
        reason: 'relationship coverage',
        metadata: ['source' => 'test'],
    );

    $profile
        ->addMedia(UploadedFile::fake()->image('portfolio.jpg'))
        ->toMediaCollection(ArtisanProfile::PORTFOLIO_COLLECTION);

    expect(ArtisanServiceStatus::cases())->toHaveCount(5);
    expect(FieldVisitStatus::cases())->toHaveCount(6);
    expect(KycRiskLevel::cases())->toHaveCount(3);
    expect($category->children()->firstOrFail()->is($childCategory))->toBeTrue();
    expect($childCategory->parent()->firstOrFail()->is($category))->toBeTrue();
    expect($childCategory->artisanServices()->firstOrFail()->is($service))->toBeTrue();
    expect($service->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($service->category()->firstOrFail()->is($childCategory))->toBeTrue();
    expect($service->status)->toBe(ArtisanServiceStatus::Active);
    expect($service->starting_price)->toBe('25000.00');
    expect($profile->services()->firstOrFail()->is($service))->toBeTrue();
    expect($profile->kycSubmissions()->firstOrFail()->is($submission))->toBeTrue();
    expect($profile->fieldVisits()->firstOrFail()->is($visit))->toBeTrue();
    expect($profile->statusHistories()->firstOrFail()->is($history))->toBeTrue();
    expect($profile->getFirstMedia(ArtisanProfile::PORTFOLIO_COLLECTION))->not->toBeNull();
    expect(KycSubmission::mediaCollectionNames())->toBe([
        'government_id',
        'self_portrait',
        'address_evidence',
        'business_registration',
    ]);
    expect($submission->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($submission->reviewedBy()->firstOrFail()->is($reviewer))->toBeTrue();
    expect($submission->fieldVisits()->firstOrFail()->is($visit))->toBeTrue();
    expect($submission->statusHistories()->count())->toBe(0);
    expect($submission->status)->toBe(ArtisanVerificationStatus::Submitted);
    expect($submission->risk_level)->toBe(KycRiskLevel::Medium);
    expect($visit->kycSubmission()->firstOrFail()->is($submission))->toBeTrue();
    expect($visit->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($visit->areaAgent()->firstOrFail()->is($agent))->toBeTrue();
    expect($visit->territory()->firstOrFail()->id)->toBe($profile->territory_id);
    expect($visit->status)->toBe(FieldVisitStatus::Completed);
    expect($visit->checklist)->toBe(['shop_exists' => true, 'identity_seen' => false]);
    expect($history->statusable()->firstOrFail()->is($profile))->toBeTrue();
    expect($history->actor()->firstOrFail()->is($owner))->toBeTrue();
    expect($history->metadata)->toBe(['source' => 'test']);
    expect($owner->statusHistories()->firstOrFail()->is($history))->toBeTrue();
    expect($reviewer->reviewedKycSubmissions()->firstOrFail()->is($submission))->toBeTrue();
    expect($agent->fieldVisitsConducted()->firstOrFail()->is($visit))->toBeTrue();
});

test('phase three actions update listing services kyc media and field visit status flows', function () {
    Storage::fake('local');

    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $agent = $business['agent'];
    $category = $business['category'];

    $updatedProfile = app(UpsertArtisanProfile::class)->handle(
        profile: $profile,
        actor: $owner,
        businessName: ' Phase Three Sparks ',
        publicSummary: ' Reliable electrical repairs. ',
        yearsExperience: 8,
        serviceRadiusKm: 25,
        publicPhone: ' +2348031234567 ',
        publicEmail: 'sparks@example.com',
        availabilityStatus: ArtisanAvailabilityStatus::Online,
        isPublic: true,
    );
    $service = app(CreateArtisanService::class)->handle(
        profile: $updatedProfile,
        category: $category,
        title: ' Safety inspection ',
        description: 'Home and shop safety checks.',
        startingPrice: '20000',
        currencyCode: 'ngn',
        status: ArtisanServiceStatus::Active,
    );
    $submission = app(SubmitKyc::class)->handle($updatedProfile, $owner, ' Ready for review ');
    $media = app(AttachKycMedia::class)->handle(
        submission: $submission,
        file: UploadedFile::fake()->image('identity.jpg'),
        collectionName: KycSubmission::GOVERNMENT_ID_COLLECTION,
        actor: $owner,
    );
    $submittedStatus = $submission->status;
    $submittedNotes = $submission->notes;
    $scheduledVisit = app(RecordFieldVisit::class)->handle(
        profile: $updatedProfile,
        areaAgent: $agent,
        submission: $submission->refresh(),
        territory: $updatedProfile->territory()->firstOrFail(),
        status: FieldVisitStatus::Scheduled,
        notes: 'Visit booked.',
    );
    $completedVisit = app(RecordFieldVisit::class)->handle(
        profile: $updatedProfile->refresh(),
        areaAgent: $agent,
        submission: $submission->refresh(),
        territory: $updatedProfile->territory()->firstOrFail(),
        status: FieldVisitStatus::Completed,
        visitedAt: now(),
        latitude: '9.0764780',
        longitude: '7.4686590',
        notes: 'Shop confirmed.',
        checklist: ['shop_exists' => true],
    );
    $failedVisit = app(RecordFieldVisit::class)->handle(
        profile: $updatedProfile->refresh(),
        areaAgent: $agent,
        status: FieldVisitStatus::Failed,
    );

    expect($updatedProfile->business_name)->toBe('Phase Three Sparks');
    expect($updatedProfile->availability_status)->toBe(ArtisanAvailabilityStatus::Online);
    expect($updatedProfile->is_public)->toBeTrue();
    expect($service->title)->toBe('Safety inspection');
    expect($service->currency_code)->toBe('NGN');
    expect($service->status)->toBe(ArtisanServiceStatus::Active);
    expect($submittedStatus)->toBe(ArtisanVerificationStatus::Submitted);
    expect($submittedNotes)->toBe('Ready for review');
    expect($media->getCustomProperty('uploaded_by'))->toBe($owner->id);
    expect($scheduledVisit->status)->toBe(FieldVisitStatus::Scheduled);
    expect($completedVisit->status)->toBe(FieldVisitStatus::Completed);
    expect($completedVisit->statusHistories()->firstOrFail()->to_status)->toBe(FieldVisitStatus::Completed->value);
    expect($failedVisit->status)->toBe(FieldVisitStatus::Failed);
    expect($updatedProfile->refresh()->verification_status)->toBe(ArtisanVerificationStatus::FieldCheckComplete);
    expect($submission->refresh()->status)->toBe(ArtisanVerificationStatus::FieldCheckComplete);
    expect(StatusHistory::query()->where('reason', 'artisan.profile.availability_updated')->exists())->toBeTrue();
    expect(StatusHistory::query()->where('reason', 'kyc.field_visit_updated')->exists())->toBeTrue();
});

test('phase three actions reject invalid catalog kyc and visit input', function () {
    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $category = $business['category'];
    $inactiveCategory = ServiceCategory::factory()->inactive()->create();
    $otherSubmission = KycSubmission::factory()->create();
    $otherTerritory = Territory::query()->where('slug', 'abaji-market')->firstOrFail();

    expect(fn () => app(RecordStatusHistory::class)->handle($profile, null, null, ' '))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(UpsertArtisanProfile::class)->handle($profile, $owner, ' '))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateArtisanService::class)->handle($profile, $category, ' '))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateArtisanService::class)->handle($profile, $inactiveCategory, 'Repair'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CreateArtisanService::class)->handle($profile, $category, 'Repair', currencyCode: 'NAIRA'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(AttachKycMedia::class)->handle(
        KycSubmission::factory()->create(['status' => ArtisanVerificationStatus::Approved]),
        UploadedFile::fake()->image('id.jpg'),
        KycSubmission::GOVERNMENT_ID_COLLECTION,
        $owner,
    ))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(AttachKycMedia::class)->handle(
        KycSubmission::factory()->create(['artisan_profile_id' => $profile->id]),
        UploadedFile::fake()->image('id.jpg'),
        'unsupported',
        $owner,
    ))->toThrow(InvalidArgumentException::class);

    app(SubmitKyc::class)->handle($profile, $owner);

    expect(fn () => app(SubmitKyc::class)->handle($profile->refresh(), $owner))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RecordFieldVisit::class)->handle(
        profile: $profile,
        areaAgent: $owner,
        submission: $otherSubmission,
    ))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RecordFieldVisit::class)->handle(
        profile: $profile,
        areaAgent: $owner,
        territory: $otherTerritory,
    ))->toThrow(InvalidArgumentException::class);
});

test('phase three policies allow scoped work and deny destructive operations', function () {
    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $agent = $business['agent'];
    $customer = User::factory()->create();
    $customer->assignRole(PlatformRole::Customer->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(PlatformRole::SuperAdmin->value);
    $service = ArtisanService::factory()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => $business['category']->id,
    ]);
    $submission = KycSubmission::factory()->submitted()->create(['artisan_profile_id' => $profile->id]);
    $visit = FieldVisit::factory()->create([
        'artisan_profile_id' => $profile->id,
        'kyc_submission_id' => $submission->id,
        'area_agent_id' => $agent->id,
    ]);

    expect(Gate::forUser($owner)->allows('viewAny', ArtisanService::class))->toBeTrue();
    expect(Gate::forUser($owner)->allows('view', $service))->toBeTrue();
    expect(Gate::forUser($owner)->allows('create', [ArtisanService::class, $profile]))->toBeTrue();
    expect(Gate::forUser($owner)->allows('update', $service))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('create', [ArtisanService::class, $profile]))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('update', $service))->toBeTrue();
    expect(Gate::forUser($customer)->denies('viewAny', ArtisanService::class))->toBeTrue();
    expect(Gate::forUser($customer)->denies('delete', $service))->toBeTrue();
    expect(Gate::forUser($customer)->denies('restore', $service))->toBeTrue();
    expect(Gate::forUser($customer)->denies('forceDelete', $service))->toBeTrue();

    expect(Gate::forUser($owner)->allows('viewAny', KycSubmission::class))->toBeTrue();
    expect(Gate::forUser($owner)->allows('view', $submission))->toBeTrue();
    expect(Gate::forUser($owner)->allows('create', [KycSubmission::class, $profile]))->toBeTrue();
    expect(Gate::forUser($owner)->allows('update', $submission))->toBeTrue();
    expect(Gate::forUser($agent)->allows('create', [KycSubmission::class, $profile]))->toBeTrue();
    expect(Gate::forUser($agent)->allows('update', $submission))->toBeTrue();
    expect(Gate::forUser($customer)->denies('viewAny', KycSubmission::class))->toBeTrue();
    expect(Gate::forUser($customer)->denies('delete', $submission))->toBeTrue();
    expect(Gate::forUser($customer)->denies('restore', $submission))->toBeTrue();
    expect(Gate::forUser($customer)->denies('forceDelete', $submission))->toBeTrue();

    expect(Gate::forUser($agent)->allows('viewAny', FieldVisit::class))->toBeTrue();
    expect(Gate::forUser($agent)->allows('view', $visit))->toBeTrue();
    expect(Gate::forUser($agent)->allows('create', [FieldVisit::class, $profile]))->toBeTrue();
    expect(Gate::forUser($agent)->allows('update', $visit))->toBeTrue();
    expect(Gate::forUser($customer)->denies('viewAny', FieldVisit::class))->toBeTrue();
    expect(Gate::forUser($customer)->denies('delete', $visit))->toBeTrue();
    expect(Gate::forUser($customer)->denies('restore', $visit))->toBeTrue();
    expect(Gate::forUser($customer)->denies('forceDelete', $visit))->toBeTrue();
});

test('phase three artisan pages expose inertia contracts and accept form submissions', function () {
    Storage::fake('local');
    Storage::fake('public');

    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $team = $profile->team()->firstOrFail();
    $category = $business['category'];

    $this
        ->actingAs($owner)
        ->get(route('artisan.dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Dashboard')
            ->where('profile.businessName', 'Phase Three Electrical')
            ->where('metrics.services', 0)
            ->where('latestKyc', null));

    $this
        ->actingAs($owner)
        ->get(route('artisan.profile.edit', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Profile')
            ->where('profile.businessName', 'Phase Three Electrical')
            ->where('profile.portfolio', [])
            ->has('availabilityStatuses', 4));

    $this
        ->actingAs($owner)
        ->post(route('artisan.profile.update', ['current_team' => $team]), [
            '_method' => 'PATCH',
            'business_name' => 'Updated Phase Three Electrical',
            'public_summary' => 'Updated listing summary',
            'years_experience' => 9,
            'service_radius_km' => 30,
            'public_phone' => '+2348031234567',
            'public_email' => 'updated@example.com',
            'availability_status' => 'online',
            'is_public' => '1',
        ])
        ->assertRedirect(route('artisan.profile.edit', ['current_team' => $team]));

    $this
        ->actingAs($owner)
        ->post(route('artisan.profile.portfolio.store', ['current_team' => $team]), [
            'portfolio' => UploadedFile::fake()->image('work.png'),
        ])
        ->assertRedirect(route('artisan.profile.edit', ['current_team' => $team]));

    $this
        ->actingAs($owner)
        ->get(route('artisan.services.index', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Services')
            ->has('categories', 1)
            ->where('categories.0.name', 'Electrical'));

    $this
        ->actingAs($owner)
        ->post(route('artisan.services.store', ['current_team' => $team]), [
            'service_category_id' => $category->id,
            'title' => 'Panel repair',
            'description' => 'Breaker and panel fault isolation.',
            'starting_price' => '35000',
            'currency_code' => 'NGN',
            'status' => 'active',
        ])
        ->assertRedirect(route('artisan.services.index', ['current_team' => $team]));

    $this
        ->actingAs($owner)
        ->get(route('artisan.services.index', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Services')
            ->where('services.0.title', 'Panel repair')
            ->where('services.0.category.name', 'Electrical'));

    $this
        ->actingAs($owner)
        ->get(route('artisan.kyc.show', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Kyc')
            ->where('latestSubmission', null)
            ->has('collections', 4));

    $this
        ->actingAs($owner)
        ->post(route('artisan.kyc.store', ['current_team' => $team]), [
            'notes' => 'Submitted from route.',
            'government_id' => UploadedFile::fake()->image('identity.jpg'),
        ])
        ->assertRedirect(route('artisan.kyc.show', ['current_team' => $team]));

    $this
        ->actingAs($owner)
        ->get(route('artisan.kyc.show', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Kyc')
            ->where('latestSubmission.status', 'submitted')
            ->where('latestSubmission.media.government_id.fileName', 'identity.jpg')
            ->where('latestSubmission.media.self_portrait', null));

    $this
        ->actingAs($owner)
        ->get(route('artisan.dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artisan/Dashboard')
            ->where('metrics.services', 1)
            ->where('latestKyc.status', 'submitted')
            ->where('recentServices.0.title', 'Panel repair'));

    expect(ArtisanProfile::query()->firstOrFail()->is_public)->toBeTrue();
    expect(ArtisanService::query()->where('title', 'Panel repair')->exists())->toBeTrue();
    expect(KycSubmission::query()->firstOrFail()->getFirstMedia(KycSubmission::GOVERNMENT_ID_COLLECTION))->not->toBeNull();
});

test('phase three field visit route records scoped visits', function () {
    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $agent = $business['agent'];
    $team = $profile->team()->firstOrFail();
    $team->members()->syncWithoutDetaching([
        $agent->id => ['role' => TeamRole::Member->value],
    ]);
    $agent->switchTeam($team);
    $submission = app(SubmitKyc::class)->handle($profile, $owner);

    $this
        ->actingAs($agent)
        ->post(route('artisan.field-visits.store', ['current_team' => $team]), [
            'kyc_submission_id' => $submission->id,
            'territory_id' => $profile->territory_id,
            'status' => 'completed',
            'visited_at' => '2026-05-30 09:00:00',
            'latitude' => '9.0764780',
            'longitude' => '7.4686590',
            'notes' => 'Shop verified.',
            'checklist' => [
                'shop_exists' => true,
                'identity_seen' => true,
            ],
        ])
        ->assertRedirect(route('artisan.kyc.show', ['current_team' => $team]));

    $this
        ->actingAs($agent)
        ->post(route('artisan.field-visits.store', ['current_team' => $team]), [
            'status' => 'in_progress',
            'notes' => 'Follow-up visit started.',
        ])
        ->assertRedirect(route('artisan.kyc.show', ['current_team' => $team]));

    $visit = FieldVisit::query()->firstOrFail();

    expect($visit->status)->toBe(FieldVisitStatus::Completed);
    expect($visit->checklist)->toBe(['shop_exists' => true, 'identity_seen' => true]);
    expect(FieldVisit::query()->count())->toBe(2);
    expect($submission->refresh()->status)->toBe(ArtisanVerificationStatus::FieldCheckComplete);
});

test('phase three validation rejects invalid profile service kyc and field visit payloads', function () {
    $business = phaseThreeBusiness();
    $profile = $business['profile'];
    $owner = $business['owner'];
    $team = $profile->team()->firstOrFail();
    $inactiveCategory = ServiceCategory::factory()->inactive()->create();

    $this
        ->actingAs($owner)
        ->post(route('artisan.profile.update', ['current_team' => $team]), [
            '_method' => 'PATCH',
            'business_name' => '',
            'availability_status' => 'not-real',
        ])
        ->assertSessionHasErrors(['business_name', 'availability_status']);

    $this
        ->actingAs($owner)
        ->post(route('artisan.services.store', ['current_team' => $team]), [
            'service_category_id' => $inactiveCategory->id,
            'title' => '',
            'currency_code' => 'NGN',
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['service_category_id', 'title']);

    $this
        ->actingAs($owner)
        ->post(route('artisan.kyc.store', ['current_team' => $team]), [
            'government_id' => UploadedFile::fake()->create('script.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('government_id');
});

test('phase three artisan controllers guard direct calls without an artisan context', function () {
    $controller = app(DashboardController::class);

    expect(fn () => $controller(Request::create('/artisan', 'GET')))
        ->toThrow(HttpException::class);

    $noTeamUser = User::query()->create([
        'name' => 'No Current Team',
        'email' => 'no-current-team@example.com',
        'password' => 'password',
    ]);
    $requestWithoutTeam = Request::create('/artisan', 'GET');
    $requestWithoutTeam->setUserResolver(fn () => $noTeamUser);

    expect(fn () => $controller($requestWithoutTeam))
        ->toThrow(NotFoundHttpException::class);

    $personalTeamUser = User::factory()->create();
    $requestWithPersonalTeam = Request::create('/artisan', 'GET');
    $requestWithPersonalTeam->setUserResolver(fn () => $personalTeamUser);

    expect(fn () => $controller($requestWithPersonalTeam))
        ->toThrow(NotFoundHttpException::class);
});

test('pilot user seed data includes phase three catalog and kyc records', function () {
    $this->seed(PilotUserSeeder::class);
    $this->seed(PilotUserSeeder::class);

    $profile = ArtisanProfile::query()->firstOrFail();
    $service = ArtisanService::query()->firstOrFail();
    $submission = KycSubmission::query()->firstOrFail();

    expect(ServiceCategory::query()->count())->toBe(3);
    expect(ArtisanService::query()->count())->toBe(1);
    expect(KycSubmission::query()->count())->toBe(1);
    expect($service->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($service->status)->toBe(ArtisanServiceStatus::Active);
    expect($submission->artisanProfile()->firstOrFail()->is($profile))->toBeTrue();
    expect($submission->status)->toBe(ArtisanVerificationStatus::Submitted);
});
