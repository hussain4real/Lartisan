<?php

use App\Contracts\Payments\PaymentProvider;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\KycSubmission;
use App\Models\Payment;
use App\Models\ServiceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TeamInvitation as TeamInvitationModel;
use App\Models\WaitlistEntry;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Notifications\Waitlists\WaitlistJoined;
use App\Support\PrivateMediaUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route as RouteFacade;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

test('phase eight operational commands are configured for cloud workers and scheduler maintenance', function (): void {
    expect(config('lartisan.hardening.queue_worker_command'))->toBe('php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=60')
        ->and(config('lartisan.hardening.scheduler_command'))->toBe('php artisan schedule:run')
        ->and(config('lartisan.hardening.scheduler_cache_store'))->toBe('database');

    Artisan::call('schedule:list');

    $schedule = Artisan::output();

    expect($schedule)
        ->toContain('queue:prune-failed --hours=168')
        ->toContain('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168');
});

test('phase eight named throttles protect sensitive public and upload routes', function (string $routeName, string $middleware): void {
    $route = RouteFacade::getRoutes()->getByName($routeName);

    if (! $route instanceof Route) {
        throw new RuntimeException("Route [{$routeName}] was not registered.");
    }

    expect($route->gatherMiddleware())->toContain($middleware);
})->with([
    'account claim' => ['account-claim.store', 'throttle:account-claims'],
    'booking tracker confirm' => ['booking-tracker.confirm', 'throttle:booking-tracker-actions'],
    'identity otp issue' => ['identity.otp.issue', 'throttle:identity-otp'],
    'identity otp verify' => ['identity.otp.verify', 'throttle:identity-otp'],
    'marketplace booking' => ['marketplace.bookings.store', 'throttle:marketplace-bookings'],
    'paystack webhook' => ['webhooks.paystack', 'throttle:paystack-webhooks'],
    'portfolio upload' => ['artisan.profile.portfolio.store', 'throttle:media-uploads'],
    'kyc upload' => ['artisan.kyc.store', 'throttle:media-uploads'],
    'waitlist submission' => ['waitlist.store', 'throttle:waitlist-submissions'],
]);

test('phase eight migration adds hardening indexes for marketplace operations and provider queues', function (): void {
    expect(sqliteIndexNames('artisan_profiles'))->toContain('lartisan_artisan_profiles_marketplace_idx')
        ->and(sqliteIndexNames('artisan_profiles'))->toContain('lartisan_artisan_profiles_location_idx')
        ->and(sqliteIndexNames('artisan_services'))->toContain('lartisan_artisan_services_marketplace_idx')
        ->and(sqliteIndexNames('subscriptions'))->toContain('lartisan_subscriptions_active_lookup_idx')
        ->and(sqliteIndexNames('bookings'))->toContain('lartisan_bookings_status_schedule_idx')
        ->and(sqliteIndexNames('bookings'))->toContain('lartisan_bookings_lga_status_idx')
        ->and(sqliteIndexNames('kyc_submissions'))->toContain('lartisan_kyc_queue_idx')
        ->and(sqliteIndexNames('disputes'))->toContain('lartisan_disputes_queue_idx')
        ->and(sqliteIndexNames('payouts'))->toContain('lartisan_payouts_queue_idx')
        ->and(sqliteIndexNames('provider_webhook_events'))->toContain('lartisan_webhook_events_processing_idx');
});

test('private kyc media returns a short lived signed url after the artisan is authorized', function (): void {
    $context = createTeamManagementContext();
    $submission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => $context['profile']->id,
    ]);

    $submission
        ->addMedia(UploadedFile::fake()->image('government-id.jpg'))
        ->toMediaCollection(KycSubmission::GOVERNMENT_ID_COLLECTION);

    $this
        ->actingAs($context['owner'])
        ->get(route('artisan.kyc.show', ['current_team' => $context['team']->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Kyc')
            ->where('latestSubmission.media.government_id.fileName', 'government-id.jpg')
            ->where('latestSubmission.media.government_id.url', fn (string $url): bool => str_contains($url, 'expires=')
                && str_contains($url, 'signature=')));
});

test('private media url generation reports storage provider failures', function (): void {
    $submission = KycSubmission::factory()->submitted()->create();

    $submission
        ->addMedia(UploadedFile::fake()->image('government-id.jpg'))
        ->toMediaCollection(KycSubmission::GOVERNMENT_ID_COLLECTION);

    $media = $submission->getFirstMedia(KycSubmission::GOVERNMENT_ID_COLLECTION);

    if (! $media instanceof Media) {
        throw new RuntimeException('KYC media was not stored.');
    }

    $media->forceFill(['disk' => 'missing-private-disk']);

    Log::shouldReceive('warning')
        ->once()
        ->with('Provider operation failed.', Mockery::on(fn (array $context): bool => $context['provider'] === 'storage'
            && $context['operation'] === 'private-media-temporary-url'
            && $context['disk'] === 'missing-private-disk'
            && $context['media_id'] === $media->id));

    expect(fn () => app(PrivateMediaUrl::class)->for($media))->toThrow(InvalidArgumentException::class);
});

test('payment provider failures are logged with provider context', function (): void {
    config()->set('services.paystack.secret_key', 'test-secret');
    config()->set('services.paystack.payment_url', 'https://paystack-fail.test');

    Http::preventStrayRequests();
    Http::fake([
        'https://paystack-fail.test/*' => Http::response(['status' => false], 503),
    ]);

    $payment = Payment::factory()->create();

    Log::shouldReceive('warning')
        ->once()
        ->with('Provider operation failed.', Mockery::on(fn (array $context): bool => $context['provider'] === 'paystack'
            && $context['operation'] === 'transaction-initialize'
            && $context['payment_id'] === $payment->id
            && $context['reference'] === $payment->reference
            && $context['status'] === 503));

    expect(fn () => app(PaymentProvider::class)->initialize($payment, 'https://lartisan.test/payments/callback'))
        ->toThrow(RuntimeException::class);
});

test('payment provider connection exceptions are logged before rethrowing', function (): void {
    config()->set('services.paystack.secret_key', 'test-secret');
    config()->set('services.paystack.payment_url', 'https://paystack-exception.test');

    Http::preventStrayRequests();
    Http::fake([
        'https://paystack-exception.test/*' => fn () => throw new RuntimeException('Connection refused.'),
    ]);

    $payment = Payment::factory()->create();

    Log::shouldReceive('warning')
        ->once()
        ->with('Provider operation failed.', Mockery::on(fn (array $context): bool => $context['provider'] === 'paystack'
            && $context['operation'] === 'transaction-initialize'
            && $context['failure'] === 'Connection refused.'
            && $context['exception'] === RuntimeException::class
            && $context['payment_id'] === $payment->id
            && $context['reference'] === $payment->reference));

    expect(fn () => app(PaymentProvider::class)->initialize($payment, 'https://lartisan.test/payments/callback'))
        ->toThrow(RuntimeException::class, 'Connection refused.');
});

test('queued notifications remain provider fakeable for phase eight flows', function (): void {
    $entry = WaitlistEntry::factory()->create();
    $teamInvitation = TeamInvitationModel::factory()->create();

    expect(new WaitlistJoined($entry))->toBeInstanceOf(ShouldQueue::class)
        ->and(new TeamInvitationNotification($teamInvitation))->toBeInstanceOf(ShouldQueue::class);

    Notification::fake();

    Notification::route('mail', $entry->email)->notify(new WaitlistJoined($entry));

    Notification::assertSentOnDemand(WaitlistJoined::class);
});

test('phase eight marketplace indexes support the active artisan query shape', function (): void {
    $profile = createPhaseEightMarketplaceProfile();
    $category = $profile->services()->firstOrFail()->category()->firstOrFail();

    $matches = ArtisanProfile::query()
        ->where('verification_status', ArtisanVerificationStatus::Approved)
        ->where('subscription_status', ArtisanSubscriptionStatus::Active)
        ->where('availability_status', '!=', ArtisanAvailabilityStatus::Vacation)
        ->where('is_public', true)
        ->whereHas('subscriptions', fn ($query) => $query
            ->where('status', 'active')
            ->where('ends_at', '>', now()))
        ->whereHas('services', fn ($query) => $query
            ->where('status', ArtisanServiceStatus::Active)
            ->where('service_category_id', $category->id))
        ->get();

    $matchedProfile = $matches->first();

    if (! $matchedProfile instanceof ArtisanProfile) {
        throw new RuntimeException('No marketplace profile was matched.');
    }

    expect($matches)->toHaveCount(1)
        ->and($matchedProfile->is($profile))->toBeTrue();
});

/**
 * @return array<int, string>
 */
function sqliteIndexNames(string $table): array
{
    return collect(DB::select("PRAGMA index_list('{$table}')"))
        ->pluck('name')
        ->filter(fn (mixed $name): bool => is_string($name))
        ->values()
        ->all();
}

function createPhaseEightMarketplaceProfile(): ArtisanProfile
{
    $profile = ArtisanProfile::factory()->create([
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'is_public' => true,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'verification_status' => ArtisanVerificationStatus::Approved,
    ]);

    $plan = SubscriptionPlan::factory()->create(['active' => true]);

    Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => $plan->id,
    ]);

    ArtisanService::factory()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => ServiceCategory::factory()->create(['active' => true])->id,
        'status' => ArtisanServiceStatus::Active,
    ]);

    return $profile->refresh();
}
