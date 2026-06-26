<?php

use App\Actions\Notifications\SendLifecycleNotification;
use App\Actions\Notifications\SendNotificationDelivery;
use App\Actions\Operations\CollectSystemHealthSnapshot;
use App\Actions\Operations\PruneOperationalNoise;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:send-subscription-reminders {--days=7}', function (): void {
    $days = max(1, (int) $this->option('days'));
    $deliveries = app(SendLifecycleNotification::class)->sendSubscriptionReminders($days);

    $this->info('Queued '.count($deliveries).' subscription reminder delivery records.');
})->purpose('Send subscription reminder notifications');

Artisan::command('notifications:retry-failed {--limit=50}', function (): void {
    $limit = max(1, (int) $this->option('limit'));
    $sender = app(SendNotificationDelivery::class);
    $deliveries = NotificationDelivery::query()
        ->where('status', NotificationDeliveryStatus::Failed->value)
        ->oldest('failed_at')
        ->limit($limit)
        ->get();

    $deliveries->each(fn (NotificationDelivery $delivery): NotificationDelivery => $sender->retry($delivery));

    $this->info('Retried '.$deliveries->count().' failed notification delivery records.');
})->purpose('Retry failed notification delivery records');

Artisan::command('notifications:dead-letter-failed {--attempts=3}', function (): void {
    $attempts = max(1, (int) $this->option('attempts'));
    $sender = app(SendNotificationDelivery::class);
    $deliveries = NotificationDelivery::query()
        ->where('status', NotificationDeliveryStatus::Failed->value)
        ->where('attempts', '>=', $attempts)
        ->get();

    $deliveries->each(fn (NotificationDelivery $delivery): NotificationDelivery => $sender->markDeadLetter($delivery, 'Exceeded notification retry attempts.'));

    $this->info('Dead-lettered '.$deliveries->count().' failed notification delivery records.');
})->purpose('Move repeatedly failed notification deliveries to dead letter status');

Artisan::command('operations:collect-health', function (): void {
    $snapshot = app(CollectSystemHealthSnapshot::class)->handle();

    $this->info('Collected system health snapshot #'.$snapshot->id.' with status '.$snapshot->status->value.'.');
})->purpose('Collect queue, provider, storage, scheduler, backup, restore-test, and retention health signals');

Artisan::command('operations:prune-noise', function (): void {
    $counts = app(PruneOperationalNoise::class)->handle();

    collect($counts)->each(function (int $count, string $table): void {
        $this->line("Pruned {$count} {$table} record(s).");
    });
})->purpose('Prune configured operational-noise records while retaining sensitive records');

$schedulerCacheStore = config('lartisan.hardening.scheduler_cache_store');

Schedule::useCache(is_string($schedulerCacheStore) && $schedulerCacheStore !== '' ? $schedulerCacheStore : 'database');

Schedule::call(function (): void {
    $freshnessMinutes = config('lartisan.observability.scheduler_freshness_minutes');
    $ttlMinutes = (is_numeric($freshnessMinutes) ? (int) $freshnessMinutes : 90) + 10;

    Cache::put('lartisan.scheduler.last_seen_at', now()->toISOString(), now()->addMinutes($ttlMinutes));
})
    ->name('lartisan.scheduler.heartbeat')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('operations:collect-health')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('operations:prune-noise')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:run --disable-notifications --no-interaction')
    ->dailyAt('01:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:monitor --no-interaction')
    ->dailyAt('01:45')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:clean --disable-notifications --no-interaction')
    ->dailyAt('02:45')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('notifications:send-subscription-reminders --days=7')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();
