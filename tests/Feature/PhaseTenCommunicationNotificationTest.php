<?php

use App\Actions\Bookings\AcceptBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Actions\Notifications\SendNotificationDelivery;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\BookingStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Enums\PayoutStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\NotificationDelivery;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Review;
use App\Models\ServiceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportCase;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\TransactionalNotification;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
    config()->set('lartisan.notifications.default_channels', ['email']);
    config()->set('lartisan.notifications.channels.email.enabled', true);
    config()->set('lartisan.notifications.channels.whatsapp.enabled', false);
});

test('notification deliveries use provider abstractions and dedupe outbound attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'phase.ten.customer@example.test',
        'name' => 'Phase Ten Customer',
    ]);
    $booking = Booking::factory()->forCustomer($user)->create();
    $sender = app(SendNotificationDelivery::class);

    $delivery = $sender->handle(
        eventType: NotificationEventType::BookingStatusChanged,
        channel: NotificationChannel::Email,
        recipientAddress: $user->email,
        subject: 'Booking Accepted',
        body: 'Your booking was accepted.',
        recipient: $user,
        source: $booking,
        recipientName: $user->name,
        dedupeKey: 'phase-ten-booking-accepted',
    );
    $duplicate = $sender->handle(
        eventType: NotificationEventType::BookingStatusChanged,
        channel: NotificationChannel::Email,
        recipientAddress: $user->email,
        subject: 'Booking Accepted',
        body: 'Your booking was accepted again.',
        recipient: $user,
        source: $booking,
        recipientName: $user->name,
        dedupeKey: 'phase-ten-booking-accepted',
    );
    $skipped = $sender->handle(
        eventType: NotificationEventType::BookingStatusChanged,
        channel: NotificationChannel::Whatsapp,
        recipientAddress: '+2348030001111',
        subject: 'Booking Accepted',
        body: 'Your booking was accepted.',
        recipient: $user,
        source: $booking,
        recipientName: $user->name,
    );

    expect($delivery->status)->toBe(NotificationDeliveryStatus::Sent);
    expect($delivery->channel)->toBe(NotificationChannel::Email);
    expect($delivery->event_type)->toBe(NotificationEventType::BookingStatusChanged);
    expect($delivery->provider)->toBe('mail');
    expect($delivery->provider_message_id)->toBe('mail-'.$delivery->id);
    expect($delivery->attempts)->toBe(1);
    expect($delivery->recipient()->firstOrFail()->is($user))->toBeTrue();
    expect($delivery->source()->firstOrFail()->is($booking))->toBeTrue();
    expect($duplicate->id)->toBe($delivery->id);
    expect($skipped->status)->toBe(NotificationDeliveryStatus::Skipped);
    expect($skipped->attempts)->toBe(0);
    expect(NotificationDelivery::query()->where('dedupe_key', 'phase-ten-booking-accepted')->count())->toBe(1);

    $notification = new TransactionalNotification($delivery);

    expect($notification->via(new stdClass))->toBe(['mail']);
    expect($notification->toMail(new stdClass))->toBeInstanceOf(MailMessage::class);
    expect($notification->toArray(new stdClass))->toMatchArray([
        'delivery_id' => $delivery->id,
        'event_type' => NotificationEventType::BookingStatusChanged->value,
        'source_id' => $booking->id,
        'source_type' => $booking->getMorphClass(),
    ]);
});

test('whatsapp provider records success failures retries and dead letters', function (): void {
    config()->set('lartisan.notifications.channels.whatsapp.enabled', true);
    config()->set('lartisan.notifications.channels.whatsapp.base_url', 'https://whatsapp.test');
    config()->set('lartisan.notifications.channels.whatsapp.token', 'secret-token');
    config()->set('lartisan.notifications.channels.whatsapp.retry_times', 0);
    Http::preventStrayRequests();
    $attemptsByRecipient = [];
    Http::fake(function (HttpRequest $request) use (&$attemptsByRecipient) {
        $recipientValue = $request['recipient'];
        $recipient = is_scalar($recipientValue) ? (string) $recipientValue : '';
        $attemptsByRecipient[$recipient] = ($attemptsByRecipient[$recipient] ?? 0) + 1;

        if ($recipient === '+2348030002222') {
            return Http::response([
                'message_id' => 'wamid.phase-ten-success',
                'status' => 'sent',
            ]);
        }

        if ($recipient === '+2348030003333' && $attemptsByRecipient[$recipient] === 1) {
            return Http::response(['error' => 'provider down'], 500);
        }

        if ($recipient === '+2348030006666') {
            return Http::response('accepted');
        }

        return Http::response([
            'id' => 'wamid.phase-ten-retry',
            'status' => 'sent',
        ]);
    });

    $sender = app(SendNotificationDelivery::class);
    $sent = $sender->handle(
        eventType: NotificationEventType::PayoutStatusChanged,
        channel: NotificationChannel::Whatsapp,
        recipientAddress: '+2348030002222',
        subject: 'Payout Paid',
        body: 'Your payout was paid.',
    );

    expect($sent->status)->toBe(NotificationDeliveryStatus::Sent);
    expect($sent->provider_message_id)->toBe('wamid.phase-ten-success');
    expect($sent->provider_status)->toBe('sent');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://whatsapp.test/messages'
        && $request->data()['recipient'] === '+2348030002222'
        && $request->data()['subject'] === 'Payout Paid');

    $failed = $sender->handle(
        eventType: NotificationEventType::PayoutStatusChanged,
        channel: NotificationChannel::Whatsapp,
        recipientAddress: '+2348030003333',
        subject: 'Payout Failed',
        body: 'Your payout could not be processed.',
    );

    expect($failed->status)->toBe(NotificationDeliveryStatus::Failed);
    expect($failed->attempts)->toBe(1);
    expect($failed->failure_reason)->toContain('status 500');

    $retried = $sender->retry($failed);

    expect($retried->status)->toBe(NotificationDeliveryStatus::Sent);
    expect($retried->attempts)->toBe(2);
    expect($retried->provider_message_id)->toBe('wamid.phase-ten-retry');

    $plainTextResponse = $sender->handle(
        eventType: NotificationEventType::PayoutStatusChanged,
        channel: NotificationChannel::Whatsapp,
        recipientAddress: '+2348030006666',
        subject: 'Payout Queued',
        body: 'Your payout notification was accepted.',
    );

    expect($plainTextResponse->status)->toBe(NotificationDeliveryStatus::Sent);
    expect($plainTextResponse->provider_message_id)->toBeNull();
    expect(data_get($plainTextResponse->metadata, 'provider_receipt'))->toBe([]);

    $deadLetter = $sender->markDeadLetter($retried, 'Ops reviewed repeated provider failures.');

    expect($deadLetter->status)->toBe(NotificationDeliveryStatus::DeadLettered);
    expect($deadLetter->failure_reason)->toBe('Ops reviewed repeated provider failures.');
});

test('whatsapp callbacks validate signatures and update delivery logs', function (): void {
    /** @var TestCase $this */
    config()->set('lartisan.notifications.channels.whatsapp.webhook_secret', 'phase-ten-webhook-secret');
    $delivery = NotificationDelivery::factory()->whatsapp()->create([
        'provider_message_id' => 'wamid.phase-ten-callback',
        'status' => NotificationDeliveryStatus::Sent,
    ]);
    $payload = [
        'message_id' => 'wamid.phase-ten-callback',
        'recipient' => '+2348030004444',
        'status' => 'delivered',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this
        ->call(
            'POST',
            route('webhooks.whatsapp'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_LARTISAN_WHATSAPP_SIGNATURE' => 'invalid',
            ],
            $body,
        )
        ->assertForbidden();
    expect($delivery->refresh()->status)->toBe(NotificationDeliveryStatus::Sent);

    $this
        ->call(
            'POST',
            route('webhooks.whatsapp'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_LARTISAN_WHATSAPP_SIGNATURE' => hash_hmac('sha256', $body, 'phase-ten-webhook-secret'),
            ],
            $body,
        )
        ->assertOk()
        ->assertJson(['ok' => true]);

    $delivery->refresh();
    expect($delivery->status)->toBe(NotificationDeliveryStatus::Delivered);
    expect($delivery->callback_received_at)->not->toBeNull();
    expect(data_get($delivery->metadata, 'callback.status'))->toBe('delivered');

    $unknownPayload = [
        'message_id' => 'wamid.phase-ten-unknown',
        'recipient' => '+2348030005555',
        'status' => 'failed',
    ];
    $unknownBody = json_encode($unknownPayload, JSON_THROW_ON_ERROR);

    $this
        ->call(
            'POST',
            route('webhooks.whatsapp'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_LARTISAN_WHATSAPP_SIGNATURE' => hash_hmac('sha256', $unknownBody, 'phase-ten-webhook-secret'),
            ],
            $unknownBody,
        )
        ->assertOk();

    $unknownDelivery = NotificationDelivery::query()
        ->where('provider_message_id', 'wamid.phase-ten-unknown')
        ->firstOrFail();

    expect($unknownDelivery->event_type)->toBe(NotificationEventType::ProviderCallback);
    expect($unknownDelivery->status)->toBe(NotificationDeliveryStatus::Failed);
    expect($unknownDelivery->recipient_address)->toBe('+2348030005555');

    $readPayload = ['status' => 'read'];
    $readBody = json_encode($readPayload, JSON_THROW_ON_ERROR);

    $this
        ->call(
            'POST',
            route('webhooks.whatsapp'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_LARTISAN_WHATSAPP_SIGNATURE' => hash_hmac('sha256', $readBody, 'phase-ten-webhook-secret'),
            ],
            $readBody,
        )
        ->assertOk();

    $readDelivery = NotificationDelivery::query()
        ->whereNull('provider_message_id')
        ->latest('id')
        ->firstOrFail();

    expect($readDelivery->status)->toBe(NotificationDeliveryStatus::Read);
    expect($readDelivery->recipient_address)->toBe('unknown');
});

test('lifecycle notification action covers booking subscription payout review dispute and support events', function (): void {
    config()->set('lartisan.notifications.default_channels', ['email', 'whatsapp', 'not-a-channel', false]);
    $owner = User::factory()->create([
        'email' => 'phase.ten.owner@example.test',
        'name' => 'Phase Ten Owner',
    ]);
    $customer = User::factory()->create([
        'email' => 'phase.ten.customer.lifecycle@example.test',
        'name' => 'Phase Ten Customer',
    ]);
    $profile = ArtisanProfile::factory()->create([
        'business_name' => 'Phase Ten Makers',
        'public_phone' => '+2348030007777',
        'user_id' => $owner->id,
    ]);
    $booking = Booking::factory()->forCustomer($customer)->create([
        'artisan_profile_id' => $profile->id,
        'tracker_code' => 'BK-PHASETEN',
    ]);
    $subscription = Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'ends_at' => now()->addDays(7),
        'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
    ]);
    $wallet = Wallet::factory()->create(['artisan_profile_id' => $profile->id]);
    $payout = Payout::factory()->create([
        'artisan_profile_id' => $profile->id,
        'amount' => 250000,
        'currency_code' => 'NGN',
        'payout_account_id' => PayoutAccount::factory()->create(['artisan_profile_id' => $profile->id])->id,
        'status' => PayoutStatus::Paid,
        'wallet_id' => $wallet->id,
    ]);
    $review = Review::factory()->create([
        'artisan_profile_id' => $profile->id,
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'rating' => 5,
    ]);
    $dispute = Dispute::factory()->create([
        'artisan_profile_id' => $profile->id,
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'opened_by_id' => $customer->id,
        'severity' => DisputeSeverity::High,
        'status' => DisputeStatus::EscalatedToState,
        'subject' => 'Phase Ten dispute',
    ]);
    $supportCase = SupportCase::factory()->create([
        'category' => SupportCaseCategory::Dispute,
        'owner_id' => $owner->id,
        'priority' => SupportCasePriority::High,
        'requester_id' => $customer->id,
        'status' => SupportCaseStatus::Resolved,
        'subject' => 'Phase Ten support case',
    ]);
    $notifier = app(SendLifecycleNotification::class);

    $notifier->bookingStatusChanged($booking, BookingStatus::Accepted);
    $notifier->subscriptionActivated($subscription);
    $notifier->subscriptionReminder($subscription, 7);
    $notifier->subscriptionReminder($subscription, 7);
    $notifier->sendSubscriptionReminders(7);
    $notifier->payoutStatusChanged($payout);
    $notifier->reviewSubmitted($review);
    $notifier->disputeOpened($dispute);
    $notifier->disputeEscalated($dispute);
    $notifier->disputeResolved($dispute);
    $notifier->supportCaseOpened($supportCase);
    $notifier->supportCaseResolved($supportCase);

    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::BookingStatusChanged->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::SubscriptionActivated->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::SubscriptionReminder->value)->count())->toBe(1);
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::PayoutStatusChanged->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::ReviewSubmitted->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::DisputeOpened->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::DisputeEscalated->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::DisputeResolved->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::SupportCaseOpened->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('event_type', NotificationEventType::SupportCaseResolved->value)->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('channel', NotificationChannel::Whatsapp->value)->where('status', NotificationDeliveryStatus::Skipped->value)->exists())->toBeTrue();
});

test('booking actions dispatch requested and accepted notification deliveries', function (): void {
    $owner = User::factory()->create(['email' => 'phase.ten.action.owner@example.test']);
    $customer = User::factory()->create(['email' => 'phase.ten.action.customer@example.test']);
    $profile = ArtisanProfile::factory()->create([
        'availability_status' => ArtisanAvailabilityStatus::Online,
        'is_public' => true,
        'subscription_status' => ArtisanSubscriptionStatus::Active,
        'user_id' => $owner->id,
        'verification_status' => ArtisanVerificationStatus::Approved,
    ]);
    $category = ServiceCategory::factory()->create();
    $service = ArtisanService::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'service_category_id' => $category->id,
        'starting_price' => '15000.00',
    ]);
    Subscription::factory()->active()->create([
        'artisan_profile_id' => $profile->id,
        'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
    ]);

    $created = app(CreateBooking::class)->handle(
        profile: $profile,
        service: $service,
        customer: $customer,
        customerName: 'Phase Ten Action Customer',
        customerPhone: '+2348030008888',
        customerEmail: 'phase.ten.action.customer@example.test',
        addressSnapshot: ['line_1' => 'Phase Ten Street'],
        scheduledAt: now()->addDay(),
        description: 'Install notification wiring.',
    );
    $accepted = app(AcceptBooking::class)->handle($created->booking, $owner);

    expect($accepted->status)->toBe(BookingStatus::Accepted);
    expect(NotificationDelivery::query()->where('dedupe_key', "booking:{$created->booking->id}:requested")->exists())->toBeTrue();
    expect(NotificationDelivery::query()->where('dedupe_key', "booking:{$created->booking->id}:accepted")->exists())->toBeTrue();
});
