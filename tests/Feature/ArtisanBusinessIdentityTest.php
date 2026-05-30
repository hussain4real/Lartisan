<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformRole;
use App\Models\ArtisanProfile;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Database\QueryException;

test('artisan business profile action creates a non personal team workspace', function () {
    $this->seed(PlatformAccessSeeder::class);

    $owner = User::factory()->create();
    $agent = User::factory()->create();

    $profile = app(CreateArtisanBusinessProfile::class)->handle(
        owner: $owner,
        businessName: ' Bright Sparks Electrical ',
        onboardedByAgent: $agent,
        internalNotes: 'Agent assisted onboarding',
    );

    $team = $profile->team()->firstOrFail();
    $onboardedByAgent = $profile->onboardedByAgent()->firstOrFail();
    $profileFromTeam = $team->artisanProfile()->firstOrFail();
    $ownerFresh = User::query()->findOrFail($owner->id);

    expect($profile->business_name)->toBe('Bright Sparks Electrical');
    expect($profile->verification_status)->toBe(ArtisanVerificationStatus::Draft);
    expect($profile->subscription_status)->toBe(ArtisanSubscriptionStatus::Trial);
    expect($profile->availability_status)->toBe(ArtisanAvailabilityStatus::Offline);
    expect($profile->internal_notes)->toBe('Agent assisted onboarding');
    expect($onboardedByAgent->is($agent))->toBeTrue();
    expect($team->name)->toBe('Bright Sparks Electrical');
    expect($team->is_personal)->toBeFalse();
    expect($team->owner()?->is($owner))->toBeTrue();
    expect($profileFromTeam->is($profile))->toBeTrue();
    expect($ownerFresh->current_team_id)->toBe($team->id);
    expect($ownerFresh->hasRole(PlatformRole::Artisan->value))->toBeTrue();
    expect($owner->artisanProfiles()->firstOrFail()->is($profile))->toBeTrue();
    expect($agent->onboardedArtisanProfiles()->firstOrFail()->is($profile))->toBeTrue();
});

test('a user may own multiple artisan business profiles while personal teams stay separate', function () {
    $this->seed(PlatformAccessSeeder::class);

    $owner = User::factory()->create();
    $personalTeam = $owner->personalTeam();

    $first = app(CreateArtisanBusinessProfile::class)->handle($owner, 'Northside Tailors');
    $second = app(CreateArtisanBusinessProfile::class)->handle($owner, 'Northside Repairs');
    $firstTeam = $first->team()->firstOrFail();
    $secondTeam = $second->team()->firstOrFail();

    expect($owner->artisanProfiles()->count())->toBe(2);
    expect($firstTeam->is_personal)->toBeFalse();
    expect($secondTeam->is_personal)->toBeFalse();
    expect($personalTeam?->artisanProfile)->toBeNull();
});

test('artisan profile casts status and approval fields', function () {
    $owner = User::factory()->create();
    $approver = User::factory()->create();

    $profile = ArtisanProfile::factory()->create([
        'user_id' => $owner->id,
        'verification_status' => ArtisanVerificationStatus::Approved,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'approved_by' => $approver->id,
        'approved_at' => now(),
        'is_public' => true,
    ]);
    $approvedAt = $profile->approved_at;
    $profileOwner = $profile->user()->firstOrFail();
    $profileApprover = $profile->approvedBy()->firstOrFail();

    expect($profile->verification_status)->toBe(ArtisanVerificationStatus::Approved);
    expect($profile->subscription_status)->toBe(ArtisanSubscriptionStatus::Active);
    expect($profile->availability_status)->toBe(ArtisanAvailabilityStatus::Online);
    expect($profile->is_public)->toBeTrue();
    expect($approvedAt?->isToday())->toBeTrue();
    expect($profileOwner->is($owner))->toBeTrue();
    expect($profileApprover->is($approver))->toBeTrue();
    expect($approver->approvedArtisanProfiles()->firstOrFail()->is($profile))->toBeTrue();
});

test('artisan profile team id is unique', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();

    ArtisanProfile::factory()->create([
        'team_id' => $team->id,
        'user_id' => $owner->id,
    ]);

    expect(fn () => ArtisanProfile::factory()->create([
        'team_id' => $team->id,
        'user_id' => $owner->id,
    ]))->toThrow(QueryException::class);
});

test('artisan business creation rejects blank business names', function () {
    $owner = User::factory()->create();

    expect(fn () => app(CreateArtisanBusinessProfile::class)->handle($owner, '   '))
        ->toThrow(InvalidArgumentException::class);
});
