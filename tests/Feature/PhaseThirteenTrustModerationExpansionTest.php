<?php

use App\Actions\Bookings\ReleaseWalletBalance;
use App\Actions\Disputes\OpenDispute;
use App\Actions\Disputes\ResolveDispute;
use App\Actions\Payments\EscrowBookingPayment;
use App\Actions\Reviews\RespondToReview;
use App\Actions\Reviews\SubmitVerifiedReview;
use App\Enums\BookingStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\DisputeTargetType;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SupportCase;
use App\Models\Team;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Policies\ReviewPolicy;
use Database\Seeders\PilotUserSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    $this->seed(PilotUserSeeder::class);
    config()->set('lartisan.notifications.default_channels', ['email']);
    config()->set('lartisan.notifications.channels.email.enabled', true);
    config()->set('lartisan.notifications.channels.whatsapp.enabled', false);
});

/**
 * @return array{
 *     superAdmin: User,
 *     localGovernmentAdmin: User,
 *     artisan: User,
 *     customer: User,
 *     profile: ArtisanProfile,
 *     service: ArtisanService,
 *     team: Team
 * }
 */
function phaseThirteenContext(): array
{
    $profile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();

    return [
        'superAdmin' => User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail(),
        'localGovernmentAdmin' => User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail(),
        'artisan' => User::query()->where('email', 'artisan@lartisan.test')->firstOrFail(),
        'customer' => User::query()->where('email', 'customer@lartisan.test')->firstOrFail(),
        'profile' => $profile,
        'service' => $profile->services()->firstOrFail(),
        'team' => $profile->team()->firstOrFail(),
    ];
}

/**
 * @param  array{customer: User, profile: ArtisanProfile, service: ArtisanService}  $context
 */
function phaseThirteenSettledBooking(array $context, int $amount = 2500000): Booking
{
    $booking = Booking::factory()
        ->accepted()
        ->forCustomer($context['customer'])
        ->create([
            'artisan_profile_id' => $context['profile']->id,
            'artisan_service_id' => $context['service']->id,
            'service_category_id' => $context['service']->service_category_id,
            'country_id' => $context['profile']->country_id,
            'state_id' => $context['profile']->state_id,
            'local_government_id' => $context['profile']->local_government_id,
            'territory_id' => $context['profile']->territory_id,
            'quoted_amount' => $amount,
            'currency_code' => 'NGN',
        ]);
    $payment = Payment::factory()
        ->booking($booking)
        ->successful()
        ->create([
            'amount' => $amount,
            'currency_code' => 'NGN',
        ]);

    app(EscrowBookingPayment::class)->handle($payment);
    $booking->forceFill([
        'confirmed_at' => now(),
        'status' => BookingStatus::Confirmed,
    ])->save();
    app(ReleaseWalletBalance::class)->handle($booking);

    return $booking->refresh();
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  class-string<TComponent>  $component
 * @param  array<string, mixed>  $params
 * @return Testable<TComponent>
 */
function phaseThirteenLivewire(User $user, string $component, array $params = []): Testable
{
    Filament::setCurrentPanel('admin');
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

function phaseThirteenRecordKey(object $record): string
{
    $key = method_exists($record, 'getKey') ? $record->getKey() : null;

    assert(is_int($key) || is_string($key));

    return (string) $key;
}

test('suspicious reviews collect private proof and route to moderation before publishing', function (): void {
    $context = phaseThirteenContext();
    $booking = phaseThirteenSettledBooking($context);
    $review = app(SubmitVerifiedReview::class)->handle(
        booking: $booking,
        customer: $context['customer'],
        rating: 1,
        comment: 'Unsafe scam work damaged the switchboard.',
        proof: [UploadedFile::fake()->image('switchboard-damage.jpg')],
    );
    $supportCase = $review->supportCases()->firstOrFail();
    $policy = new ReviewPolicy;

    expect($review->status)->toBe(ReviewStatus::PendingModeration);
    expect($review->moderation_signal)->toBe('low_rating_keyword');
    expect($review->moderation_score)->toBe(80);
    expect($review->getMedia(Review::PROOF_COLLECTION))->toHaveCount(1);
    expect($supportCase->category)->toBe(SupportCaseCategory::Safety);
    expect($supportCase->priority)->toBe(SupportCasePriority::High);
    expect($supportCase->status)->toBe(SupportCaseStatus::Open);

    $this->actingAs($context['superAdmin']);
    Filament::setCurrentPanel('admin');

    expect(ReviewResource::canAccess())->toBeTrue();
    expect(ReviewResource::canCreate())->toBeFalse();
    expect(array_keys(ReviewResource::getPages()))->toBe(['index', 'view']);
    expect(ReviewResource::getEloquentQuery()->whereKey($review->id)->exists())->toBeTrue();
    expect($policy->view($context['superAdmin'], $review))->toBeTrue();
    expect($policy->update($context['superAdmin'], $review))->toBeTrue();

    phaseThirteenLivewire($context['superAdmin'], ListReviews::class)
        ->assertCanSeeTableRecords([$review])
        ->callTableAction('approve', phaseThirteenRecordKey($review), [
            'notes' => 'Evidence reviewed and review can be published.',
        ])
        ->assertHasNoTableActionErrors();

    $review->refresh();
    expect($review->status)->toBe(ReviewStatus::Published);
    expect($review->supportCases()->firstOrFail()->status)->toBe(SupportCaseStatus::Resolved);
    expect(AuditLog::query()->where('action', 'review.moderated')->where('subject_id', $review->id)->exists())->toBeTrue();
});

test('artisans can respond to visible reviews through the team route and hidden reviews reject responses', function (): void {
    $context = phaseThirteenContext();
    $booking = phaseThirteenSettledBooking($context);
    $review = app(SubmitVerifiedReview::class)->handle(
        booking: $booking,
        customer: $context['customer'],
        rating: 5,
        comment: 'Excellent repair and tidy work.',
    );

    $this->actingAs($context['artisan'])
        ->post(route('artisan.reviews.response.store', [
            'current_team' => $context['team']->slug,
            'review' => $review,
        ]), [
            'response' => 'Thank you for trusting our team.',
        ])
        ->assertRedirect(route('artisan.bookings.index', ['current_team' => $context['team']->slug]));

    $review->refresh();
    expect($review->artisan_response)->toBe('Thank you for trusting our team.');
    expect($review->artisan_response_by)->toBe($context['artisan']->id);
    expect($review->artisan_responded_at)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'review.responded')->where('subject_id', $review->id)->exists())->toBeTrue();

    $review->forceFill(['status' => ReviewStatus::Hidden])->save();

    expect(fn () => app(RespondToReview::class)->handle($review, $context['artisan'], 'Hidden follow-up.'))
        ->toThrow(AuthorizationException::class, 'Hidden reviews cannot receive public artisan responses.');
});

test('review profile and payment dispute targets are tracked with audited money adjustments', function (): void {
    $context = phaseThirteenContext();
    $booking = phaseThirteenSettledBooking($context);
    $payment = $booking->payments()->firstOrFail();
    $review = app(SubmitVerifiedReview::class)->handle(
        booking: $booking,
        customer: $context['customer'],
        rating: 4,
        comment: 'The work was completed but billing needs review.',
    );
    $openDispute = app(OpenDispute::class);

    expect(fn () => $openDispute->handle(
        booking: $booking,
        actor: $context['customer'],
        subject: 'Wrong payment',
        payment: Payment::factory()->successful()->create(),
    ))->toThrow(InvalidArgumentException::class, 'The selected payment does not belong to this booking.');

    $reviewDispute = $openDispute->handle(
        booking: $booking,
        actor: $context['customer'],
        subject: 'Review dispute',
        review: $review,
    );
    $paymentDispute = $openDispute->handle(
        booking: $booking,
        actor: $context['customer'],
        subject: 'Payment reference dispute',
        description: 'Payment receipt needs reconciliation.',
        severity: DisputeSeverity::High,
        payment: $payment,
    );
    $profileDispute = $openDispute->handle(
        booking: phaseThirteenSettledBooking($context),
        actor: $context['localGovernmentAdmin'],
        subject: 'Profile compliance review',
        target: DisputeTargetType::Profile,
    );

    expect($reviewDispute->target)->toBe(DisputeTargetType::Review);
    expect($review->refresh()->status)->toBe(ReviewStatus::Disputed);
    expect($paymentDispute->target)->toBe(DisputeTargetType::Payment);
    expect($paymentDispute->payment_id)->toBe($payment->id);
    expect(data_get($paymentDispute->metadata, 'payment_reference'))->toBe($payment->reference);
    expect($profileDispute->target)->toBe(DisputeTargetType::Profile);

    $availableBeforeAdjustment = $context['profile']->wallet()->firstOrFail()->available_balance;
    $resolved = app(ResolveDispute::class)->handle(
        dispute: $paymentDispute,
        actor: $context['superAdmin'],
        resolution: 'Approved ledger adjustment after payment reconciliation.',
        moneyAdjustmentAmount: 50000,
        moneyAdjustmentDirection: WalletLedgerDirection::Debit,
    );
    $ledgerEntry = $resolved->moneyAdjustmentLedgerEntry()->firstOrFail();

    expect($resolved->status)->toBe(DisputeStatus::Resolved);
    expect($resolved->money_adjustment_amount)->toBe(50000);
    expect($resolved->money_adjustment_direction)->toBe(WalletLedgerDirection::Debit);
    expect($ledgerEntry->type)->toBe(WalletLedgerEntryType::AdjustmentDebit);
    expect($ledgerEntry->direction)->toBe(WalletLedgerDirection::Debit);
    expect($ledgerEntry->available_balance_after)->toBe($availableBeforeAdjustment - 50000);
    expect($ledgerEntry->immutable_reference)->toBe('dispute-'.$resolved->id.'-money-adjustment');
    expect($ledgerEntry->source()->firstOrFail()->is($resolved))->toBeTrue();
    expect(WalletLedgerEntry::query()->where('immutable_reference', $ledgerEntry->immutable_reference)->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'dispute.resolved')->where('subject_id', $resolved->id)->exists())->toBeTrue();
});

test('customer forms accept review proof and expose payment dispute targets', function (): void {
    $context = phaseThirteenContext();
    $booking = phaseThirteenSettledBooking($context);
    $payment = $booking->payments()->firstOrFail();

    $this->actingAs($context['customer'])
        ->get(route('customer.bookings.disputes.create', $booking))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/DisputeCreate')
            ->where('booking.id', $booking->id)
            ->where('payment.id', $payment->id)
            ->where('payment.reference', $payment->reference));

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.reviews.store', $booking), [
            'rating' => 1,
            'comment' => 'Unsafe scam behavior after the job.',
            'proof' => [UploadedFile::fake()->image('proof.png')],
        ])
        ->assertRedirect(route('customer.bookings.show', $booking));

    $review = $booking->review()->firstOrFail();

    expect($review->status)->toBe(ReviewStatus::PendingModeration);
    expect($review->getMedia(Review::PROOF_COLLECTION))->toHaveCount(1);
    expect(SupportCase::query()->where('supportable_type', $review->getMorphClass())->where('supportable_id', $review->id)->exists())->toBeTrue();
});
