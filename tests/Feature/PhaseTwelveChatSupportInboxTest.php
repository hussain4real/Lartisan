<?php

use App\Actions\Bookings\PostBookingMessage;
use App\Actions\SupportCases\AddSupportCaseNote;
use App\Actions\SupportCases\AssignSupportCase;
use App\Actions\SupportCases\UpdateSupportCaseStatus;
use App\Enums\BookingMessageSenderRole;
use App\Enums\BookingStatus;
use App\Enums\NotificationChannel;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Filament\Resources\SupportCases\Pages\ListSupportCases;
use App\Filament\Resources\SupportCases\Pages\ViewSupportCase;
use App\Filament\Resources\SupportCases\SupportCaseResource;
use App\Filament\Resources\SupportCases\Tables\SupportCasesTable;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\NotificationDelivery;
use App\Models\SupportCase;
use App\Models\SupportCaseNote;
use App\Models\Team;
use App\Models\User;
use App\Policies\BookingMessagePolicy;
use App\Policies\SupportCasePolicy;
use Database\Seeders\PilotUserSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(PilotUserSeeder::class);
    config()->set('lartisan.notifications.default_channels', ['email']);
    config()->set('lartisan.notifications.channels.email.enabled', true);
    config()->set('lartisan.notifications.channels.whatsapp.enabled', false);
});

/**
 * @return array{
 *     superAdmin: User,
 *     stateCoordinator: User,
 *     localGovernmentAdmin: User,
 *     areaAgent: User,
 *     artisan: User,
 *     customer: User,
 *     stranger: User,
 *     profile: ArtisanProfile,
 *     service: ArtisanService,
 *     team: Team
 * }
 */
function phaseTwelveContext(): array
{
    $profile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();

    return [
        'superAdmin' => User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail(),
        'stateCoordinator' => User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail(),
        'localGovernmentAdmin' => User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail(),
        'areaAgent' => User::query()->where('email', 'area.agent@lartisan.test')->firstOrFail(),
        'artisan' => User::query()->where('email', 'artisan@lartisan.test')->firstOrFail(),
        'customer' => User::query()->where('email', 'customer@lartisan.test')->firstOrFail(),
        'stranger' => User::factory()->create(['email' => 'phase.twelve.stranger@example.test']),
        'profile' => $profile,
        'service' => $profile->services()->firstOrFail(),
        'team' => $profile->team()->firstOrFail(),
    ];
}

/**
 * @param  array{
 *     customer: User,
 *     profile: ArtisanProfile,
 *     service: ArtisanService
 * }  $context
 */
function phaseTwelveBooking(array $context, BookingStatus $status = BookingStatus::Accepted): Booking
{
    return Booking::factory()
        ->forCustomer($context['customer'])
        ->create([
            'artisan_profile_id' => $context['profile']->id,
            'artisan_service_id' => $context['service']->id,
            'service_category_id' => $context['service']->service_category_id,
            'country_id' => $context['profile']->country_id,
            'state_id' => $context['profile']->state_id,
            'local_government_id' => $context['profile']->local_government_id,
            'territory_id' => $context['profile']->territory_id,
            'status' => $status,
            'accepted_at' => in_array($status, [
                BookingStatus::Accepted,
                BookingStatus::Paid,
                BookingStatus::Escrowed,
                BookingStatus::InProgress,
                BookingStatus::Finished,
                BookingStatus::Confirmed,
                BookingStatus::Settled,
            ], true) ? now()->subHours(2) : null,
            'paid_at' => in_array($status, [
                BookingStatus::Paid,
                BookingStatus::Escrowed,
                BookingStatus::InProgress,
                BookingStatus::Finished,
                BookingStatus::Confirmed,
                BookingStatus::Settled,
            ], true) ? now()->subHour() : null,
            'escrowed_at' => in_array($status, [
                BookingStatus::Escrowed,
                BookingStatus::InProgress,
                BookingStatus::Finished,
                BookingStatus::Confirmed,
                BookingStatus::Settled,
            ], true) ? now()->subMinutes(50) : null,
            'started_at' => in_array($status, [
                BookingStatus::InProgress,
                BookingStatus::Finished,
                BookingStatus::Confirmed,
                BookingStatus::Settled,
            ], true) ? now()->subMinutes(40) : null,
            'finished_at' => in_array($status, [
                BookingStatus::Finished,
                BookingStatus::Confirmed,
                BookingStatus::Settled,
            ], true) ? now()->subMinutes(20) : null,
            'confirmed_at' => in_array($status, [
                BookingStatus::Confirmed,
                BookingStatus::Settled,
            ], true) ? now()->subMinutes(10) : null,
            'settled_at' => $status === BookingStatus::Settled ? now() : null,
        ]);
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  class-string<TComponent>  $component
 * @param  array<string, mixed>  $params
 * @return Testable<TComponent>
 */
function phaseTwelveLivewire(User $user, string $component, array $params = [], string $panel = 'admin'): Testable
{
    Filament::setCurrentPanel($panel);
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

function phaseTwelveRecordKey(SupportCase $record): string
{
    return (string) $record->id;
}

/**
 * @return TestResponse<SymfonyResponse>
 */
function phaseTwelveTestResponse(mixed $response): TestResponse
{
    if ($response instanceof TestResponse) {
        return $response;
    }

    throw new RuntimeException('Expected a Laravel test response.');
}

test('registered customers and owning artisans exchange booking chat messages in eligible states', function (): void {
    $context = phaseTwelveContext();
    $booking = phaseTwelveBooking($context, BookingStatus::Escrowed);
    BookingMessage::factory()->create([
        'booking_id' => $booking->id,
        'sender_id' => $context['artisan']->id,
        'sender_role' => BookingMessageSenderRole::Artisan,
        'body' => 'We are ready for the job.',
        'created_at' => now()->subMinute(),
    ]);

    expect(PostBookingMessage::eligibleStatuses())->toEqual([
        BookingStatus::Requested,
        BookingStatus::Accepted,
        BookingStatus::Paid,
        BookingStatus::Escrowed,
        BookingStatus::InProgress,
    ]);
    expect(PostBookingMessage::canSend($booking))->toBeTrue();

    $customerResponse = $this->actingAs($context['customer'])
        ->get(route('customer.bookings.chat.show', ['booking' => $booking]));

    $customerResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('booking/Chat')
            ->where('role', 'customer')
            ->where('canSend', true)
            ->where('booking.id', $booking->id)
            ->where('booking.artisan.businessName', $context['profile']->business_name)
            ->where('messages.0.body', 'We are ready for the job.')
            ->missing('booking.customerPhone')
            ->missing('booking.customerEmail'));

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.chat.messages.store', ['booking' => $booking]), [
            'body' => 'I will be available after noon.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas(BookingMessage::class, [
        'booking_id' => $booking->id,
        'sender_id' => $context['customer']->id,
        'sender_role' => BookingMessageSenderRole::Customer->value,
        'body' => 'I will be available after noon.',
    ]);

    $this->actingAs($context['artisan']);
    $artisanResponse = phaseTwelveTestResponse($this->get(route('artisan.bookings.chat.show', [
        'current_team' => $context['team']->slug,
        'booking' => $booking,
    ])));

    $artisanResponse->assertOk();
    $artisanResponse->assertDontSee($booking->customer_phone);

    if (is_string($booking->customer_email)) {
        $artisanResponse->assertDontSee($booking->customer_email);
    }

    $artisanResponse->assertInertia(fn (Assert $page): Assert => $page
        ->component('booking/Chat')
        ->where('role', 'artisan')
        ->where('currentTeamSlug', $context['team']->slug)
        ->where('canSend', true)
        ->where('messages.1.body', 'I will be available after noon.'));

    $this->actingAs($context['artisan'])
        ->post(route('artisan.bookings.chat.messages.store', [
            'current_team' => $context['team']->slug,
            'booking' => $booking,
        ]), [
            'body' => 'Confirmed, see you then.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas(BookingMessage::class, [
        'booking_id' => $booking->id,
        'sender_id' => $context['artisan']->id,
        'sender_role' => BookingMessageSenderRole::Artisan->value,
        'body' => 'Confirmed, see you then.',
    ]);
    $this->assertDatabaseHas(AuditLog::class, [
        'action' => 'booking.message.created',
        'actor_id' => $context['artisan']->id,
    ]);
});

test('chat denies unrelated users contact details guest bookings and closed booking sends', function (): void {
    $context = phaseTwelveContext();
    $booking = phaseTwelveBooking($context, BookingStatus::Accepted);

    $this->actingAs($context['stranger'])
        ->get(route('customer.bookings.chat.show', ['booking' => $booking]))
        ->assertForbidden();

    $this->actingAs($context['stranger'])
        ->post(route('customer.bookings.chat.messages.store', ['booking' => $booking]), [
            'body' => 'Hello from outside.',
        ])
        ->assertForbidden();

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.chat.messages.store', ['booking' => $booking]), [
            'body' => 'Call me on +234 803 222 4455',
        ])
        ->assertSessionHasErrors('body');

    $this->assertDatabaseMissing(BookingMessage::class, [
        'booking_id' => $booking->id,
        'body' => 'Call me on +234 803 222 4455',
    ]);

    $guestBooking = Booking::factory()->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
        'customer_id' => null,
        'status' => BookingStatus::Accepted,
    ]);

    expect(PostBookingMessage::canSend($guestBooking))->toBeFalse();

    $this->actingAs($context['artisan']);
    $guestChatResponse = phaseTwelveTestResponse($this->get(route('artisan.bookings.chat.show', [
        'current_team' => $context['team']->slug,
        'booking' => $guestBooking,
    ])));

    $guestChatResponse->assertNotFound();

    $closedBooking = phaseTwelveBooking($context, BookingStatus::Finished);
    BookingMessage::factory()->create([
        'booking_id' => $closedBooking->id,
        'sender_id' => $context['customer']->id,
        'sender_role' => BookingMessageSenderRole::Customer,
        'body' => 'Thanks for the update.',
    ]);

    $this->actingAs($context['customer'])
        ->get(route('customer.bookings.chat.show', ['booking' => $closedBooking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('booking/Chat')
            ->where('canSend', false)
            ->where('messages.0.body', 'Thanks for the update.'));

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.chat.messages.store', ['booking' => $closedBooking]), [
            'body' => 'Can I still send?',
        ])
        ->assertSessionHasErrors('body');

    expect(fn () => app(PostBookingMessage::class)->handle(
        $booking,
        $context['stranger'],
        'Direct action denial.',
    ))->toThrow(AuthorizationException::class);
});

test('support inbox scopes cases and supports assignment notes and status transitions', function (): void {
    $context = phaseTwelveContext();
    $booking = phaseTwelveBooking($context, BookingStatus::Escrowed);
    $supportCase = SupportCase::factory()->create([
        'requester_id' => $context['customer']->id,
        'supportable_type' => $booking->getMorphClass(),
        'supportable_id' => $booking->id,
        'category' => SupportCaseCategory::Booking,
        'priority' => SupportCasePriority::High,
        'status' => SupportCaseStatus::Open,
        'subject' => 'Need help with booking chat',
    ]);
    $unrelatedCase = SupportCase::factory()->create([
        'subject' => 'Unrelated support case',
    ]);
    $policy = new SupportCasePolicy;

    $this->actingAs($context['superAdmin']);
    expect(SupportCaseResource::canAccess())->toBeTrue();
    expect(SupportCaseResource::canCreate())->toBeFalse();
    expect(array_keys(SupportCaseResource::getPages()))->toBe(['index', 'view']);
    expect($policy->viewAny($context['superAdmin']))->toBeTrue();
    expect($policy->view($context['superAdmin'], $supportCase))->toBeTrue();
    expect($policy->create($context['superAdmin']))->toBeFalse();
    expect($policy->delete($context['superAdmin'], $supportCase))->toBeFalse();

    phaseTwelveLivewire($context['superAdmin'], ListSupportCases::class)
        ->assertCanSeeTableRecords([$supportCase, $unrelatedCase])
        ->callTableAction('assign', phaseTwelveRecordKey($supportCase), [
            'owner_id' => $context['localGovernmentAdmin']->id,
        ])
        ->assertHasNoTableActionErrors();

    $supportCase->refresh();
    expect($supportCase->owner_id)->toBe($context['localGovernmentAdmin']->id)
        ->and($supportCase->status)->toBe(SupportCaseStatus::InProgress);

    phaseTwelveLivewire($context['localGovernmentAdmin'], ListSupportCases::class, panel: 'lga')
        ->assertCanSeeTableRecords([$supportCase])
        ->assertCanNotSeeTableRecords([$unrelatedCase])
        ->callTableAction('addInternalNote', phaseTwelveRecordKey($supportCase), [
            'body' => 'Called the artisan and kept the case in-platform.',
        ])
        ->assertHasNoTableActionErrors()
        ->callTableAction('updateStatus', phaseTwelveRecordKey($supportCase), [
            'status' => SupportCaseStatus::Resolved->value,
            'resolution_notes' => 'Customer and artisan confirmed resolution.',
        ])
        ->assertHasNoTableActionErrors();

    $supportCase->refresh();
    expect($supportCase->status)->toBe(SupportCaseStatus::Resolved)
        ->and($supportCase->resolution_notes)->toBe('Customer and artisan confirmed resolution.')
        ->and($supportCase->resolved_at)->not->toBeNull();

    $this->assertDatabaseHas(SupportCaseNote::class, [
        'support_case_id' => $supportCase->id,
        'author_id' => $context['localGovernmentAdmin']->id,
        'body' => 'Called the artisan and kept the case in-platform.',
        'is_internal' => true,
    ]);
    $this->assertDatabaseHas(AuditLog::class, [
        'action' => 'support_case.assigned',
        'subject_id' => $supportCase->id,
    ]);
    $this->assertDatabaseHas(AuditLog::class, [
        'action' => 'support_case.note.created',
        'subject_id' => $supportCase->id,
    ]);
    $this->assertDatabaseHas(AuditLog::class, [
        'action' => 'support_case.status_updated',
        'subject_id' => $supportCase->id,
    ]);
    $this->assertDatabaseHas(NotificationDelivery::class, [
        'channel' => NotificationChannel::Email->value,
        'source_type' => $supportCase->getMorphClass(),
        'source_id' => $supportCase->id,
    ]);

    phaseTwelveLivewire($context['localGovernmentAdmin'], ViewSupportCase::class, [
        'record' => phaseTwelveRecordKey($supportCase),
    ], 'lga')
        ->assertOk()
        ->assertSee('Need help with booking chat')
        ->assertSee('Called the artisan');
});

test('support inbox actions reject invalid assignees and missing resolution notes', function (): void {
    $context = phaseTwelveContext();
    $booking = phaseTwelveBooking($context, BookingStatus::Accepted);
    $supportCase = SupportCase::factory()->create([
        'requester_id' => $context['customer']->id,
        'supportable_type' => $booking->getMorphClass(),
        'supportable_id' => $booking->id,
        'status' => SupportCaseStatus::Open,
    ]);

    expect(fn () => app(AssignSupportCase::class)->handle(
        $supportCase,
        $context['superAdmin'],
        $context['customer'],
    ))->toThrow(InvalidArgumentException::class);

    app(AssignSupportCase::class)->handle(
        $supportCase,
        $context['superAdmin'],
        $context['localGovernmentAdmin'],
    );

    expect(fn () => app(AddSupportCaseNote::class)->handle(
        $supportCase->refresh(),
        $context['localGovernmentAdmin'],
        '   ',
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(UpdateSupportCaseStatus::class)->handle(
        $supportCase->refresh(),
        $context['localGovernmentAdmin'],
        SupportCaseStatus::Closed,
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(PostBookingMessage::class)->handle(
        phaseTwelveBooking($context, BookingStatus::Settled),
        $context['customer'],
        'This booking is already settled.',
    ))->toThrow(ValidationException::class);
});

test('phase twelve defensive branches and relationships stay covered', function (): void {
    $context = phaseTwelveContext();
    $booking = phaseTwelveBooking($context, BookingStatus::Accepted);
    $supportCase = SupportCase::factory()->resolved()->create([
        'requester_id' => $context['customer']->id,
        'owner_id' => $context['localGovernmentAdmin']->id,
        'supportable_type' => $booking->getMorphClass(),
        'supportable_id' => $booking->id,
    ]);
    $message = BookingMessage::factory()->create([
        'booking_id' => $booking->id,
        'sender_id' => $context['customer']->id,
    ]);
    $orphanMessage = new BookingMessage([
        'booking_id' => 0,
        'sender_id' => $context['customer']->id,
        'sender_role' => BookingMessageSenderRole::Customer,
        'body' => 'No booking relation.',
    ]);
    $note = SupportCaseNote::factory()->create([
        'support_case_id' => $supportCase->id,
        'author_id' => $context['localGovernmentAdmin']->id,
    ]);
    $bookingMessagePolicy = new BookingMessagePolicy;
    $policy = new SupportCasePolicy;

    expect(fn () => app(PostBookingMessage::class)->handle(
        $booking,
        $context['customer'],
        '   ',
    ))->toThrow(ValidationException::class);

    foreach (['Use me@example.test', 'Use https://example.test'] as $body) {
        $this->actingAs($context['customer'])
            ->post(route('customer.bookings.chat.messages.store', ['booking' => $booking]), [
                'body' => $body,
            ])
            ->assertSessionHasErrors('body');
    }

    $supportCase = app(UpdateSupportCaseStatus::class)->handle(
        $supportCase,
        $context['localGovernmentAdmin'],
        SupportCaseStatus::Closed,
        'Closing after confirmation.',
    );
    expect($supportCase->closed_at)->not->toBeNull();

    $supportCase = app(UpdateSupportCaseStatus::class)->handle(
        $supportCase,
        $context['localGovernmentAdmin'],
        SupportCaseStatus::Open,
    );
    expect($supportCase->resolved_at)->toBeNull()
        ->and($supportCase->closed_at)->toBeNull();

    auth()->logout();
    expect(SupportCaseResource::getEloquentQuery()->count())->toBe(0);

    $this->actingAs($context['superAdmin']);

    $assignActionMethod = new ReflectionMethod(SupportCasesTable::class, 'assignAction');
    $assignActionMethod->setAccessible(true);
    $assignAction = $assignActionMethod->invoke(null);

    if (! $assignAction instanceof Action) {
        throw new RuntimeException('Expected a support assignment action.');
    }

    $assignActionFunction = $assignAction->getActionFunction();

    if (! $assignActionFunction instanceof Closure) {
        throw new RuntimeException('Expected a support assignment action callback.');
    }

    expect(fn () => $assignActionFunction($supportCase, ['owner_id' => ['invalid']]))
        ->toThrow(InvalidArgumentException::class);

    $updateActionMethod = new ReflectionMethod(SupportCasesTable::class, 'updateStatusAction');
    $updateActionMethod->setAccessible(true);
    $updateAction = $updateActionMethod->invoke(null);

    if (! $updateAction instanceof Action) {
        throw new RuntimeException('Expected a support status action.');
    }

    $updateActionFunction = $updateAction->getActionFunction();

    if (! $updateActionFunction instanceof Closure) {
        throw new RuntimeException('Expected a support status action callback.');
    }

    $updateActionFunction($supportCase, [
        'status' => SupportCaseStatus::InProgress->value,
        'resolution_notes' => null,
    ]);
    expect($supportCase->refresh()->status)->toBe(SupportCaseStatus::InProgress);

    expect(fn () => $updateActionFunction($supportCase, ['status' => 123]))
        ->toThrow(InvalidArgumentException::class);

    expect($message->booking()->firstOrFail()->is($booking))->toBeTrue();
    expect($note->supportCase()->firstOrFail()->is($supportCase))->toBeTrue();
    expect($context['customer']->bookingMessages()->firstOrFail()->is($message))->toBeTrue();
    expect($context['localGovernmentAdmin']->assignedSupportCases()->firstOrFail()->is($supportCase))->toBeTrue();
    expect($context['localGovernmentAdmin']->supportCaseNotes()->firstOrFail()->is($note))->toBeTrue();
    expect($bookingMessagePolicy->viewAny($context['customer']))->toBeFalse();
    expect($bookingMessagePolicy->view($context['customer'], $message))->toBeTrue();
    expect($bookingMessagePolicy->view($context['artisan'], $message))->toBeTrue();
    expect($bookingMessagePolicy->view($context['stranger'], $message))->toBeFalse();
    expect($bookingMessagePolicy->view($context['customer'], $orphanMessage))->toBeFalse();
    expect($bookingMessagePolicy->create($context['customer']))->toBeFalse();
    expect($bookingMessagePolicy->update($context['customer'], $message))->toBeFalse();
    expect($bookingMessagePolicy->delete($context['customer'], $message))->toBeFalse();
    expect($bookingMessagePolicy->restore($context['customer'], $message))->toBeFalse();
    expect($bookingMessagePolicy->forceDelete($context['customer'], $message))->toBeFalse();
    expect($policy->restore($context['superAdmin'], $supportCase))->toBeFalse();
    expect($policy->forceDelete($context['superAdmin'], $supportCase))->toBeFalse();
});
