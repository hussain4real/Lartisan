<?php

use App\Actions\Operations\CollectSystemHealthSnapshot;
use App\Actions\Operations\PruneOperationalNoise;
use App\Actions\Operations\RecordRestoreTest;
use App\Enums\OperationalHealthStatus;
use App\Enums\PlatformRole;
use App\Enums\RestoreTestStatus;
use App\Filament\Pages\HealthDashboard;
use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\ProviderWebhookEvent;
use App\Models\RestoreTestRecord;
use App\Models\SystemHealthSnapshot;
use App\Models\User;
use Database\Seeders\PilotUserSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Backup\Config\Config as BackupConfig;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(PilotUserSeeder::class);
    config()->set('services.paystack.secret_key', 'test-paystack-secret');
    config()->set('mail.default', 'array');
    config()->set('mail.from.address', 'ops@lartisan.test');
    config()->set('lartisan.notifications.channels.whatsapp.enabled', false);
    config()->set('backup.backup.destination.disks', ['local']);
    config()->set('backup.monitor_backups.0.name', 'Lartisan');
    config()->set('backup.monitor_backups.0.disks', ['local']);
    BackupConfig::rebind();
});

function phaseFifteenSuperAdmin(): User
{
    return User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail();
}

function phaseFifteenBackupPath(): string
{
    return 'Lartisan/'.now()->format('Y-m-d-H-i-s').'.zip';
}

function phaseFifteenCreateHealthyBackup(): string
{
    $path = phaseFifteenBackupPath();
    Storage::disk('local')->put($path, 'backup-zip-bytes');

    return $path;
}

function phaseFifteenInsertFailedJob(): void
{
    if (! Schema::hasTable('failed_jobs')) {
        return;
    }

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'PhaseFifteenFailedJob'], JSON_THROW_ON_ERROR),
        'exception' => 'Synthetic failed job for health snapshot.',
        'failed_at' => now()->subMinutes(15),
    ]);
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  class-string<TComponent>  $component
 * @param  array<string, mixed>  $params
 * @return Testable<TComponent>
 */
function phaseFifteenLivewire(User $user, string $component, array $params = []): Testable
{
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

test('health collector records provider queue storage scheduler backup restore and retention signals', function (): void {
    $superAdmin = phaseFifteenSuperAdmin();
    $backupPath = phaseFifteenCreateHealthyBackup();
    Cache::put('lartisan.scheduler.last_seen_at', now()->toISOString(), now()->addMinutes(120));
    phaseFifteenInsertFailedJob();
    $restoreTest = app(RecordRestoreTest::class)->handle(
        verifiedBy: $superAdmin,
        status: RestoreTestStatus::Passed,
        databaseVerified: true,
        mediaVerified: true,
        backupDisk: 'local',
        backupPath: $backupPath,
        notes: 'Database and critical media restored in rehearsal.',
        metadata: ['environment' => 'test'],
    );

    $snapshot = app(CollectSystemHealthSnapshot::class)->handle($superAdmin);

    expect($restoreTest->status)->toBe(RestoreTestStatus::Passed);
    expect($snapshot->generated_by)->toBe($superAdmin->id);
    expect($snapshot->status)->toBe(OperationalHealthStatus::Warning);
    expect(array_keys($snapshot->checks))->toContain(
        'database',
        'queue',
        'providers',
        'storage',
        'scheduler',
        'backup',
        'restore_test',
        'nightwatch_logs',
        'retention',
    );
    expect($snapshot->checks['queue']['status'])->toBe(OperationalHealthStatus::Warning->value);
    expect($snapshot->checks['queue']['guidance'])->toContain('queue:retry <uuid>');
    expect($snapshot->checks['providers']['status'])->toBe(OperationalHealthStatus::Passing->value);
    expect($snapshot->checks['storage']['status'])->toBe(OperationalHealthStatus::Passing->value);
    expect($snapshot->checks['scheduler']['status'])->toBe(OperationalHealthStatus::Passing->value);
    expect(data_get($snapshot->checks, 'backup.metrics.commands_available'))->toBeTrue();
    expect(data_get($snapshot->checks, 'backup.metrics.healthy'))->toBeTrue();
    expect(data_get($snapshot->checks, 'backup.metrics.last_successful_backup_at'))->not->toBeNull();
    expect($snapshot->checks['restore_test']['status'])->toBe(OperationalHealthStatus::Passing->value);
    expect(data_get($snapshot->checks, 'nightwatch_logs.metrics.sources'))->toHaveKey('nightwatch');
    expect($snapshot->checks['retention']['status'])->toBe(OperationalHealthStatus::Passing->value);
    expect($snapshot->queue_failed_jobs_count)->toBe(Schema::hasTable('failed_jobs') ? 1 : 0);
});

test('restore-test recording requires complete database and media verification for passing status', function (): void {
    $superAdmin = phaseFifteenSuperAdmin();

    expect(fn () => app(RecordRestoreTest::class)->handle(
        verifiedBy: $superAdmin,
        status: RestoreTestStatus::Passed,
        databaseVerified: true,
        mediaVerified: false,
    ))->toThrow(InvalidArgumentException::class, 'Passed restore tests must verify both database and critical media.');

    $failed = app(RecordRestoreTest::class)->handle(
        verifiedBy: $superAdmin,
        status: RestoreTestStatus::Failed,
        databaseVerified: true,
        mediaVerified: false,
        notes: 'Media restore failed.',
    );

    expect($failed->status)->toBe(RestoreTestStatus::Failed);
    expect($failed->verified_by)->toBe($superAdmin->id);
    expect($failed->media_verified)->toBeFalse();
});

test('health dashboard is restricted to the admin panel super admin and can collect snapshots', function (): void {
    $superAdmin = phaseFifteenSuperAdmin();
    $localGovernmentAdmin = User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail();
    phaseFifteenCreateHealthyBackup();
    Cache::put('lartisan.scheduler.last_seen_at', now()->toISOString(), now()->addMinutes(120));
    RestoreTestRecord::factory()->create(['verified_by' => $superAdmin->id]);

    $this->actingAs($superAdmin);
    Filament::setCurrentPanel('admin');
    expect(HealthDashboard::canAccess())->toBeTrue();

    $dashboard = phaseFifteenLivewire($superAdmin, HealthDashboard::class);

    $dashboard->assertOk();
    $dashboard->callAction('collectHealth');
    $dashboard->assertHasNoActionErrors();
    $dashboard->assertSee('Operational status');

    expect(SystemHealthSnapshot::query()->exists())->toBeTrue();

    $this->actingAs($localGovernmentAdmin);
    Filament::setCurrentPanel('lga');
    expect($localGovernmentAdmin->hasRole(PlatformRole::LocalGovernmentAdmin->value))->toBeTrue();
    expect(HealthDashboard::canAccess())->toBeFalse();
});

test('operational pruning removes noise while retaining sensitive audit records', function (): void {
    config()->set('lartisan.retention.prune_after_days.notification_deliveries', 10);
    config()->set('lartisan.retention.prune_after_days.provider_webhook_events', 10);
    config()->set('lartisan.retention.prune_after_days.system_health_snapshots', 10);

    $oldNotification = NotificationDelivery::factory()->create([
        'created_at' => now()->subDays(11),
        'updated_at' => now()->subDays(11),
    ]);
    $freshNotification = NotificationDelivery::factory()->create();
    $oldWebhook = ProviderWebhookEvent::factory()->create([
        'created_at' => now()->subDays(11),
        'updated_at' => now()->subDays(11),
    ]);
    $freshWebhook = ProviderWebhookEvent::factory()->create();
    $oldSnapshot = SystemHealthSnapshot::factory()->create([
        'created_at' => now()->subDays(11),
        'updated_at' => now()->subDays(11),
    ]);
    $freshSnapshot = SystemHealthSnapshot::factory()->create();
    $auditLog = AuditLog::factory()->create([
        'created_at' => now()->subDays(365),
    ]);

    $counts = app(PruneOperationalNoise::class)->handle();

    expect($counts)->toBe([
        'notification_deliveries' => 1,
        'provider_webhook_events' => 1,
        'system_health_snapshots' => 1,
    ]);
    expect(NotificationDelivery::query()->whereKey($oldNotification->id)->exists())->toBeFalse();
    expect(NotificationDelivery::query()->whereKey($freshNotification->id)->exists())->toBeTrue();
    expect(ProviderWebhookEvent::query()->whereKey($oldWebhook->id)->exists())->toBeFalse();
    expect(ProviderWebhookEvent::query()->whereKey($freshWebhook->id)->exists())->toBeTrue();
    expect(SystemHealthSnapshot::query()->whereKey($oldSnapshot->id)->exists())->toBeFalse();
    expect(SystemHealthSnapshot::query()->whereKey($freshSnapshot->id)->exists())->toBeTrue();
    expect(AuditLog::query()->whereKey($auditLog->id)->exists())->toBeTrue();
});

test('phase fifteen commands schedules and backup configuration are registered', function (): void {
    expect(array_keys(Artisan::all()))->toContain(
        'operations:collect-health',
        'operations:prune-noise',
        'backup:run',
        'backup:monitor',
        'backup:clean',
    );
    expect(config('backup.backup.source.files.include'))->toContain(storage_path('app/private'), storage_path('app/public'));
    expect(config('backup.backup.source.databases'))->toContain(config('database.default'));
    expect(config('backup.backup.destination.disks'))->toContain('local');

    Artisan::call('schedule:list');
    $schedule = Artisan::output();

    expect($schedule)->toContain('operations:collect-health');
    expect($schedule)->toContain('operations:prune-noise');
    expect($schedule)->toContain('backup:run');
    expect($schedule)->toContain('backup:monitor');
    expect($schedule)->toContain('backup:clean');
});
