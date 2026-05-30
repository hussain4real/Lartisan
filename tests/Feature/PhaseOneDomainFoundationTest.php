<?php

use App\Actions\Artisans\CreateArtisanBusinessProfile;
use App\Actions\Audit\RecordAuditLog;
use App\Actions\Customers\CreateCustomerProfile;
use App\Actions\Teams\CreateTeam;
use App\Enums\TeamKind;
use App\Models\AdminProfile;
use App\Models\ArtisanProfile;
use App\Models\AuditLog;
use App\Models\CustomerProfile;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PilotUserSeeder;
use Database\Seeders\PlatformAccessSeeder;
use Illuminate\Support\Facades\Gate;

test('teams expose explicit kind while preserving personal team compatibility', function () {
    $this->seed(PlatformAccessSeeder::class);

    $user = User::factory()->create();

    $personalTeam = $user->teams()->where('is_personal', true)->firstOrFail();
    $workspace = app(CreateTeam::class)->handle($user, 'Operations Workspace');
    $artisanProfile = app(CreateArtisanBusinessProfile::class)->handle($user, 'Kind Test Electrical');
    $artisanTeam = $artisanProfile->team()->firstOrFail();

    expect($personalTeam->kind)->toBe(TeamKind::Personal);
    expect($personalTeam->is_personal)->toBeTrue();
    expect($workspace->kind)->toBe(TeamKind::Workspace);
    expect($workspace->is_personal)->toBeFalse();
    expect($artisanTeam->kind)->toBe(TeamKind::ArtisanBusiness);
    expect($artisanTeam->is_personal)->toBeFalse();

    $legacyPersonal = Team::query()->create([
        'name' => 'Legacy Personal Team',
        'is_personal' => true,
    ]);
    $legacyWorkspace = Team::query()->create([
        'name' => 'Legacy Workspace Team',
        'is_personal' => false,
    ]);
    $kindOnlyPersonal = Team::query()->create([
        'name' => 'Kind Only Personal Team',
        'kind' => TeamKind::Personal,
    ]);

    expect($legacyPersonal->kind)->toBe(TeamKind::Personal);
    expect($legacyWorkspace->kind)->toBe(TeamKind::Workspace);
    expect($kindOnlyPersonal->is_personal)->toBeTrue();
});

test('customer profile action creates an idempotent customer identity profile', function () {
    $customer = User::factory()->create();

    $profile = app(CreateCustomerProfile::class)->handle($customer, preferences: [
        'preferred_channel' => 'whatsapp',
    ]);
    $updated = app(CreateCustomerProfile::class)->handle($customer, preferences: [
        'preferred_channel' => 'sms',
    ]);

    expect(CustomerProfile::query()->count())->toBe(1);
    expect($updated->is($profile))->toBeTrue();
    expect($updated->user()->firstOrFail()->is($customer))->toBeTrue();
    expect($updated->preferences)->toBe(['preferred_channel' => 'sms']);
    expect($customer->customerProfile()->firstOrFail()->is($updated))->toBeTrue();
});

test('audit log action records append only subject changes', function () {
    $actor = User::factory()->create();
    $subject = CustomerProfile::factory()->create();

    $log = app(RecordAuditLog::class)->handle(
        actor: $actor,
        action: 'customer.profile.created',
        subject: $subject,
        before: null,
        after: ['preferences' => ['preferred_channel' => 'sms']],
        reason: 'Phase one foundation test',
        ipAddress: '127.0.0.1',
        userAgent: 'Pest',
    );

    expect($log->actor()->firstOrFail()->is($actor))->toBeTrue();
    expect($log->subject()->firstOrFail()->is($subject))->toBeTrue();
    expect($log->after)->toBe(['preferences' => ['preferred_channel' => 'sms']]);
    expect($actor->auditLogs()->firstOrFail()->is($log))->toBeTrue();

    expect(fn () => $log->update(['reason' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});

test('pilot seeder creates the customer profile and team kinds required by phase one', function () {
    $this->seed(PilotUserSeeder::class);

    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();
    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();

    expect(CustomerProfile::query()->count())->toBe(1);
    expect($customer->customerProfile()->firstOrFail()->preferences)->toBe([
        'preferred_channel' => 'whatsapp',
        'service_area' => 'Wuse',
    ]);
    expect(Team::query()->where('kind', TeamKind::Personal)->count())->toBe(6);
    expect(Team::query()->where('kind', TeamKind::ArtisanBusiness)->count())->toBe(1);
    expect($artisan->artisanProfiles()->firstOrFail()->team()->firstOrFail()->kind)->toBe(TeamKind::ArtisanBusiness);
});

test('phase one policies explicitly allow scoped reads and deny destructive gaps', function () {
    $this->seed(PilotUserSeeder::class);

    $superAdmin = User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail();
    $stateCoordinator = User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail();
    $localGovernmentAdmin = User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail();
    $artisan = User::query()->where('email', 'artisan@lartisan.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer@lartisan.test')->firstOrFail();

    $artisanProfile = ArtisanProfile::query()->firstOrFail();
    $customerProfile = CustomerProfile::query()->firstOrFail();
    $adminProfile = AdminProfile::query()->where('user_id', $localGovernmentAdmin->id)->firstOrFail();
    $auditLog = app(RecordAuditLog::class)->handle($localGovernmentAdmin, 'admin.profile.viewed', $adminProfile);

    expect(Gate::forUser($artisan)->allows('viewAny', ArtisanProfile::class))->toBeTrue();
    expect(Gate::forUser($artisan)->allows('create', ArtisanProfile::class))->toBeTrue();
    expect(Gate::forUser($artisan)->allows('update', $artisanProfile))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->allows('update', $artisanProfile))->toBeTrue();
    expect(Gate::forUser($customer)->denies('viewAny', ArtisanProfile::class))->toBeTrue();
    expect(Gate::forUser($customer)->denies('create', ArtisanProfile::class))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->denies('delete', $artisanProfile))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->denies('restore', $artisanProfile))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->denies('forceDelete', $artisanProfile))->toBeTrue();

    expect(Gate::forUser($customer)->allows('viewAny', CustomerProfile::class))->toBeTrue();
    expect(Gate::forUser($customer)->allows('create', CustomerProfile::class))->toBeTrue();
    expect(Gate::forUser($customer)->allows('update', $customerProfile))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('viewAny', CustomerProfile::class))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('create', CustomerProfile::class))->toBeTrue();
    expect(Gate::forUser($superAdmin)->denies('update', $customerProfile))->toBeTrue();
    expect(Gate::forUser($customer)->denies('delete', $customerProfile))->toBeTrue();
    expect(Gate::forUser($customer)->denies('restore', $customerProfile))->toBeTrue();
    expect(Gate::forUser($customer)->denies('forceDelete', $customerProfile))->toBeTrue();

    expect(Gate::forUser($stateCoordinator)->allows('viewAny', AdminProfile::class))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->allows('create', AdminProfile::class))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->allows('update', $adminProfile))->toBeTrue();
    expect(Gate::forUser($customer)->denies('viewAny', AdminProfile::class))->toBeTrue();
    expect(Gate::forUser($customer)->denies('create', AdminProfile::class))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->denies('delete', $adminProfile))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->denies('restore', $adminProfile))->toBeTrue();
    expect(Gate::forUser($stateCoordinator)->denies('forceDelete', $adminProfile))->toBeTrue();

    expect(Gate::forUser($customer)->allows('viewAny', AuditLog::class))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->allows('view', $auditLog))->toBeTrue();
    expect(Gate::forUser($customer)->denies('create', AuditLog::class))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->denies('update', $auditLog))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->denies('delete', $auditLog))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->denies('restore', $auditLog))->toBeTrue();
    expect(Gate::forUser($localGovernmentAdmin)->denies('forceDelete', $auditLog))->toBeTrue();
});
