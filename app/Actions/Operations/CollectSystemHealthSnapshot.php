<?php

namespace App\Actions\Operations;

use App\Enums\OperationalHealthStatus;
use App\Enums\RestoreTestStatus;
use App\Models\RestoreTestRecord;
use App\Models\SystemHealthSnapshot;
use App\Models\User;
use App\Support\MediaDisk;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;
use Throwable;

class CollectSystemHealthSnapshot
{
    public function handle(?User $actor = null): SystemHealthSnapshot
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'queue' => $this->queueCheck(),
            'providers' => $this->providerCheck(),
            'storage' => $this->storageCheck(),
            'scheduler' => $this->schedulerCheck(),
            'backup' => $this->backupCheck(),
            'restore_test' => $this->restoreTestCheck(),
            'nightwatch_logs' => $this->nightwatchAndLogsCheck(),
            'retention' => $this->retentionCheck(),
        ];
        $status = $this->overallStatus($checks);
        $queueMetrics = $checks['queue']['metrics'];
        $schedulerMetrics = $checks['scheduler']['metrics'];

        return SystemHealthSnapshot::query()->create([
            'generated_by' => $actor?->id,
            'status' => $status,
            'summary' => [
                'message' => $this->summaryMessage($status),
                'recovery_guidance' => 'Use Laravel Nightwatch for exception triage, Laravel logs for provider failure context, queue:failed / queue:retry for failed jobs, and backup:monitor plus restore-test records for recovery readiness.',
            ],
            'checks' => $checks,
            'queue_failed_jobs_count' => $this->integerMetric($queueMetrics, 'failed_jobs'),
            'queue_pending_jobs_count' => $this->integerMetric($queueMetrics, 'pending_jobs'),
            'oldest_failed_job_at' => $this->dateMetric($queueMetrics, 'oldest_failed_job_at'),
            'scheduler_last_seen_at' => $this->dateMetric($schedulerMetrics, 'last_seen_at'),
            'backup_last_successful_at' => $this->dateMetric($checks['backup']['metrics'], 'last_successful_backup_at'),
            'generated_at' => now(),
        ]);
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return $this->check(OperationalHealthStatus::Passing, 'Database connection is reachable.');
        } catch (Throwable $throwable) {
            return $this->check(
                OperationalHealthStatus::Failing,
                'Database connection failed.',
                'Inspect database credentials, network access, and recent Laravel logs.',
                ['exception' => $throwable->getMessage()],
            );
        }
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function queueCheck(): array
    {
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $pendingJobs = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $oldestFailedJobAt = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->min('failed_at')
            : null;
        $failedThreshold = $this->integerConfig('lartisan.observability.queue_failed_jobs_warning_threshold', 1);
        $pendingThreshold = $this->integerConfig('lartisan.observability.queue_pending_jobs_warning_threshold', 100);
        $status = $failedJobs >= $failedThreshold || $pendingJobs >= $pendingThreshold
            ? OperationalHealthStatus::Warning
            : OperationalHealthStatus::Passing;

        return $this->check(
            $status,
            $failedJobs > 0
                ? "{$failedJobs} failed queue job(s) need review."
                : 'Queue failure table is clear.',
            'Run php artisan queue:failed, inspect Laravel logs, then retry exact failed-job UUIDs with php artisan queue:retry <uuid>.',
            [
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
                'oldest_failed_job_at' => $oldestFailedJobAt,
            ],
        );
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function providerCheck(): array
    {
        $paystackReady = filled(config('services.paystack.secret_key'));
        $mailReady = filled(config('mail.default')) && filled(config('mail.from.address'));
        $whatsappEnabled = (bool) config('lartisan.notifications.channels.whatsapp.enabled');
        $whatsappReady = $whatsappEnabled
            && filled(config('lartisan.notifications.channels.whatsapp.base_url'))
            && filled(config('lartisan.notifications.channels.whatsapp.token'))
            && filled(config('lartisan.notifications.channels.whatsapp.webhook_secret'));
        $status = $paystackReady && $mailReady && (! $whatsappEnabled || $whatsappReady)
            ? OperationalHealthStatus::Passing
            : OperationalHealthStatus::Warning;

        return $this->check(
            $status,
            $status === OperationalHealthStatus::Passing
                ? 'Provider readiness checks are configured.'
                : 'One or more provider readiness checks need configuration.',
            'Confirm Paystack payment/payout credentials, mail sender settings, WhatsApp credentials, and recent provider failure log entries.',
            [
                'paystack_payments' => $paystackReady,
                'paystack_payouts' => $paystackReady,
                'email' => $mailReady,
                'whatsapp_enabled' => $whatsappEnabled,
                'whatsapp_ready' => $whatsappReady,
            ],
        );
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function storageCheck(): array
    {
        $path = 'health/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.txt';

        try {
            $privateDisk = MediaDisk::private();
            $publicDisk = MediaDisk::portfolio();
            Storage::disk($privateDisk)->put($path, 'ok');
            $privateOk = Storage::disk($privateDisk)->exists($path);
            Storage::disk($privateDisk)->delete($path);
            Storage::disk($publicDisk)->put($path, 'ok');
            $publicOk = Storage::disk($publicDisk)->exists($path);
            Storage::disk($publicDisk)->delete($path);

            return $this->check(
                $privateOk && $publicOk ? OperationalHealthStatus::Passing : OperationalHealthStatus::Failing,
                $privateOk && $publicOk ? 'Media storage disks are writable.' : 'One or more media storage disks failed write verification.',
                'Inspect filesystem/media disk credentials, permissions, and provider failure logs.',
                [
                    'private_disk' => $privateDisk,
                    'private_writable' => $privateOk,
                    'portfolio_disk' => $publicDisk,
                    'portfolio_writable' => $publicOk,
                ],
            );
        } catch (Throwable $throwable) {
            return $this->check(
                OperationalHealthStatus::Failing,
                'Storage write verification failed.',
                'Inspect filesystem/media disk credentials, permissions, and provider failure logs.',
                ['exception' => $throwable->getMessage()],
            );
        }
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function schedulerCheck(): array
    {
        $lastSeenAt = Cache::get('lartisan.scheduler.last_seen_at');
        $lastSeen = is_string($lastSeenAt) ? Carbon::parse($lastSeenAt) : null;
        $freshnessMinutes = $this->integerConfig('lartisan.observability.scheduler_freshness_minutes', 90);
        $status = $lastSeen instanceof Carbon && $lastSeen->greaterThanOrEqualTo(now()->subMinutes($freshnessMinutes))
            ? OperationalHealthStatus::Passing
            : OperationalHealthStatus::Warning;

        return $this->check(
            $status,
            $lastSeen instanceof Carbon ? 'Scheduler heartbeat has been observed.' : 'No scheduler heartbeat has been observed yet.',
            'Confirm the scheduler process is running the configured schedule command and check Laravel logs for scheduled task failures.',
            [
                'last_seen_at' => $lastSeen?->toISOString(),
                'freshness_minutes' => $freshnessMinutes,
                'scheduler_command' => config('lartisan.hardening.scheduler_command'),
            ],
        );
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function backupCheck(): array
    {
        $commands = Artisan::all();
        $hasBackupCommands = isset($commands['backup:run'], $commands['backup:monitor'], $commands['backup:clean']);
        $includedFiles = config('backup.backup.source.files.include', []);
        $databases = config('backup.backup.source.databases', []);
        $disks = config('backup.backup.destination.disks', []);
        $destinationMetrics = $this->backupDestinationMetrics();
        $configurationReady = $hasBackupCommands
            && is_array($includedFiles)
            && $includedFiles !== []
            && is_array($databases)
            && $databases !== [];
        $status = match (true) {
            ! $configurationReady => OperationalHealthStatus::Failing,
            $destinationMetrics['checked'] === true && $destinationMetrics['healthy'] === true => OperationalHealthStatus::Passing,
            default => OperationalHealthStatus::Warning,
        };

        return $this->check(
            $status,
            match ($status) {
                OperationalHealthStatus::Passing => 'Backup commands, configuration, and monitored destinations are healthy.',
                OperationalHealthStatus::Warning => 'Backup configuration is available, but destination health needs review.',
                OperationalHealthStatus::Failing => 'Backup commands or configuration are incomplete.',
            },
            'Run php artisan backup:run, php artisan backup:monitor, php artisan backup:clean, and record restore-test results after validating database and media recovery.',
            [
                'commands_available' => $hasBackupCommands,
                'included_files' => $includedFiles,
                'databases' => $databases,
                'destination_disks' => $disks,
                ...$destinationMetrics,
            ],
        );
    }

    /**
     * @return array{checked: bool, healthy: bool, destinations: array<int, array<string, mixed>>, last_successful_backup_at: string|null, exception?: string}
     */
    private function backupDestinationMetrics(): array
    {
        try {
            $statuses = BackupDestinationStatusFactory::createForMonitorConfig(app(BackupConfig::class)->monitoredBackups);
        } catch (Throwable $throwable) {
            return [
                'checked' => false,
                'healthy' => false,
                'destinations' => [],
                'last_successful_backup_at' => null,
                'exception' => $throwable->getMessage(),
            ];
        }

        $latestBackupAt = null;
        $destinations = $statuses
            ->map(function (BackupDestinationStatus $destinationStatus) use (&$latestBackupAt): array {
                $isHealthy = $destinationStatus->isHealthy();
                $destination = $destinationStatus->backupDestination();
                $newestBackup = $destination->newestBackup();
                $newestBackupAt = $newestBackup?->date();

                if ($newestBackupAt instanceof CarbonInterface && ($latestBackupAt === null || $newestBackupAt->greaterThan($latestBackupAt))) {
                    $latestBackupAt = $newestBackupAt;
                }

                return [
                    'name' => $destination->backupName(),
                    'disk' => $destination->diskName(),
                    'reachable' => $destination->isReachable(),
                    'healthy' => $isHealthy,
                    'newest_backup_at' => $newestBackupAt?->toISOString(),
                    'newest_backup_size' => $newestBackup?->sizeInBytes(),
                    'failures' => $destinationStatus->failureMessages()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'checked' => true,
            'healthy' => $statuses->isNotEmpty() && collect($destinations)->every(fn (array $destination): bool => $destination['healthy'] === true),
            'destinations' => $destinations,
            'last_successful_backup_at' => $latestBackupAt?->toISOString(),
        ];
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function restoreTestCheck(): array
    {
        $latest = RestoreTestRecord::query()->latest('tested_at')->first();
        $freshnessDays = $this->integerConfig('lartisan.observability.restore_test_freshness_days', 30);
        $passing = $latest instanceof RestoreTestRecord
            && $latest->status === RestoreTestStatus::Passed
            && $latest->database_verified
            && $latest->media_verified
            && $latest->tested_at->greaterThanOrEqualTo(now()->subDays($freshnessDays));

        return $this->check(
            $passing ? OperationalHealthStatus::Passing : OperationalHealthStatus::Warning,
            $passing ? 'Restore-test evidence is fresh.' : 'Restore-test evidence is missing or stale.',
            'Perform a restore rehearsal for database plus critical media, then record the result in restore_test_records.',
            [
                'latest_status' => $latest?->status->value,
                'latest_tested_at' => $latest?->tested_at?->toISOString(),
                'database_verified' => $latest?->database_verified,
                'media_verified' => $latest?->media_verified,
                'freshness_days' => $freshnessDays,
            ],
        );
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function nightwatchAndLogsCheck(): array
    {
        $sources = config('lartisan.observability.monitoring_sources', []);

        return $this->check(
            OperationalHealthStatus::Passing,
            'Laravel Nightwatch and Laravel logs are configured as operational monitoring sources.',
            'Use Nightwatch for production exception triage and Laravel logs for provider failure context before recovery handoff.',
            [
                'sources' => is_array($sources) ? $sources : [],
                'log_channel' => config('logging.default'),
            ],
        );
    }

    /**
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function retentionCheck(): array
    {
        $retainIndefinitelyConfig = config('lartisan.retention.retain_indefinitely', []);
        $retainIndefinitely = collect(is_array($retainIndefinitelyConfig) ? $retainIndefinitelyConfig : [])
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->values()
            ->all();
        $pruneAfterDays = config('lartisan.retention.prune_after_days', []);
        $requiredSensitiveTables = ['audit_logs', 'payments', 'kyc_submissions', 'wallet_ledger_entries', 'disputes'];
        $missingSensitiveTables = array_values(array_diff($requiredSensitiveTables, $retainIndefinitely));
        $invalidPruneWindows = collect(is_array($pruneAfterDays) ? $pruneAfterDays : [])
            ->filter(fn (mixed $days): bool => ! $this->isPositiveIntegerLike($days))
            ->keys()
            ->values()
            ->all();
        $passing = $missingSensitiveTables === [] && $invalidPruneWindows === [];

        return $this->check(
            $passing ? OperationalHealthStatus::Passing : OperationalHealthStatus::Failing,
            $passing ? 'Retention guard rails are configured.' : 'Retention guard rails need attention.',
            'Keep sensitive audit, payment, KYC, wallet, admin, and dispute records indefinitely unless policy changes; prune only configured operational-noise records.',
            [
                'retain_indefinitely' => $retainIndefinitely,
                'prune_after_days' => $pruneAfterDays,
                'missing_sensitive_tables' => $missingSensitiveTables,
                'invalid_prune_windows' => $invalidPruneWindows,
            ],
        );
    }

    /**
     * @param  array<string, array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}>  $checks
     */
    private function overallStatus(array $checks): OperationalHealthStatus
    {
        $statuses = array_column($checks, 'status');

        if (in_array(OperationalHealthStatus::Failing->value, $statuses, true)) {
            return OperationalHealthStatus::Failing;
        }

        if (in_array(OperationalHealthStatus::Warning->value, $statuses, true)) {
            return OperationalHealthStatus::Warning;
        }

        return OperationalHealthStatus::Passing;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{status: string, message: string, guidance: string|null, metrics: array<string, mixed>}
     */
    private function check(
        OperationalHealthStatus $status,
        string $message,
        ?string $guidance = null,
        array $metrics = [],
    ): array {
        return [
            'status' => $status->value,
            'message' => $message,
            'guidance' => $guidance,
            'metrics' => $metrics,
        ];
    }

    private function isPositiveIntegerLike(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 1;
        }

        return is_string($value) && ctype_digit($value) && (int) $value >= 1;
    }

    private function summaryMessage(OperationalHealthStatus $status): string
    {
        return match ($status) {
            OperationalHealthStatus::Passing => 'All monitored systems are passing.',
            OperationalHealthStatus::Warning => 'One or more monitored systems need operational review.',
            OperationalHealthStatus::Failing => 'One or more monitored systems are failing.',
        };
    }

    private function integerConfig(string $key, int $fallback): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function integerMetric(array $metrics, string $key): int
    {
        $value = $metrics[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function dateMetric(array $metrics, string $key): ?Carbon
    {
        $value = $metrics[$key] ?? null;

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
