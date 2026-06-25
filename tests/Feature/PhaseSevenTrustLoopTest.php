<?php

use App\Actions\Bookings\ReleaseWalletBalance;
use App\Actions\Disputes\EscalateDispute;
use App\Actions\Disputes\OpenDispute;
use App\Actions\Disputes\ResolveDispute;
use App\Actions\Documents\RenderDocument;
use App\Actions\Payments\EscrowBookingPayment;
use App\Actions\Payouts\ApprovePayout;
use App\Actions\Payouts\ProcessPayout;
use App\Actions\Payouts\RequestPayout;
use App\Actions\Reports\GenerateScopedReport;
use App\Actions\Reviews\SubmitVerifiedReview;
use App\Contracts\Documents\DocumentRenderer;
use App\Enums\BookingStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\ReportSnapshotScope;
use App\Enums\ReviewStatus;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Filament\Resources\Disputes\DisputeResource;
use App\Filament\Resources\Disputes\Pages\ListDisputes;
use App\Filament\Resources\Disputes\Pages\ViewDispute;
use App\Filament\Resources\Disputes\Tables\DisputesTable;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Resources\Payouts\Pages\ViewPayout;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Filament\Resources\Payouts\Tables\PayoutsTable;
use App\Filament\Resources\ReportSnapshots\Pages\ListReportSnapshots;
use App\Filament\Resources\ReportSnapshots\Pages\ViewReportSnapshot;
use App\Filament\Resources\ReportSnapshots\ReportSnapshotResource;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\LocalGovernment;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\PayoutAttempt;
use App\Models\ReportSnapshot;
use App\Models\Review;
use App\Models\ServiceCategory;
use App\Models\SupportCase;
use App\Models\Team;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Policies\DisputePolicy;
use App\Policies\PayoutPolicy;
use App\Policies\ReportSnapshotPolicy;
use App\Services\Documents\LaravelPdfDocumentRenderer;
use App\Support\Documents\RenderedDocument;
use Database\Seeders\PilotUserSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    $this->seed(PilotUserSeeder::class);
});

/**
 * @return array{
 *     superAdmin: User,
 *     stateCoordinator: User,
 *     localGovernmentAdmin: User,
 *     areaAgent: User,
 *     artisan: User,
 *     customer: User,
 *     profile: ArtisanProfile,
 *     service: ArtisanService,
 *     team: Team
 * }
 */
function phaseSevenContext(): array
{
    $profile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();

    return [
        'superAdmin' => User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail(),
        'stateCoordinator' => User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail(),
        'localGovernmentAdmin' => User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail(),
        'areaAgent' => User::query()->where('email', 'area.agent@lartisan.test')->firstOrFail(),
        'artisan' => User::query()->where('email', 'artisan@lartisan.test')->firstOrFail(),
        'customer' => User::query()->where('email', 'customer@lartisan.test')->firstOrFail(),
        'profile' => $profile,
        'service' => $profile->services()->firstOrFail(),
        'team' => $profile->team()->firstOrFail(),
    ];
}

/**
 * @param  array{superAdmin: User, stateCoordinator: User, localGovernmentAdmin: User, areaAgent: User, artisan: User, customer: User, profile: ArtisanProfile, service: ArtisanService, team: Team}  $context
 * @param  array{customer?: User, profile?: ArtisanProfile, service?: ArtisanService, amount?: int}  $overrides
 */
function phaseSevenConfirmedBooking(array $context, array $overrides = []): Booking
{
    $profile = $overrides['profile'] ?? $context['profile'];
    $service = $overrides['service'] ?? $context['service'];
    $customer = $overrides['customer'] ?? $context['customer'];
    $amount = $overrides['amount'] ?? 2500000;

    $booking = Booking::factory()->accepted()->forCustomer($customer)->create([
        'artisan_profile_id' => $profile->id,
        'artisan_service_id' => $service->id,
        'service_category_id' => $service->service_category_id,
        'country_id' => $profile->country_id,
        'state_id' => $profile->state_id,
        'local_government_id' => $profile->local_government_id,
        'territory_id' => $profile->territory_id,
        'quoted_amount' => $amount,
        'currency_code' => 'NGN',
    ]);
    $payment = Payment::factory()->booking($booking)->successful()->create([
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
function phaseSevenLivewire(User $user, string $component, array $params = []): Testable
{
    Filament::setCurrentPanel('admin');
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

function phaseSevenRecordKey(object $record): string
{
    $key = method_exists($record, 'getKey') ? $record->getKey() : null;

    assert(is_int($key) || is_string($key));

    return (string) $key;
}

test('phase seven data model enums factories and relationships are wired', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    $review = Review::factory()->hidden()->create([
        'booking_id' => $booking->id,
        'customer_id' => $context['customer']->id,
        'artisan_profile_id' => $context['profile']->id,
        'moderated_by' => $context['superAdmin']->id,
    ]);
    $dispute = Dispute::factory()->escalated()->create([
        'booking_id' => $booking->id,
        'review_id' => $review->id,
        'artisan_profile_id' => $context['profile']->id,
        'customer_id' => $context['customer']->id,
        'opened_by_id' => $context['customer']->id,
        'assigned_to_id' => $context['localGovernmentAdmin']->id,
    ]);
    $wallet = $context['profile']->wallet()->firstOrFail();
    $payoutAccount = PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $context['profile']->id]);
    $payout = Payout::factory()->approved($context['superAdmin'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'payout_account_id' => $payoutAccount->id,
        'wallet_id' => $wallet->id,
        'requested_by' => $context['artisan']->id,
    ]);
    $attempt = PayoutAttempt::factory()->failed()->create(['payout_id' => $payout->id]);
    $supportCase = SupportCase::factory()->resolved()->create([
        'requester_id' => $context['customer']->id,
        'owner_id' => $context['superAdmin']->id,
        'supportable_type' => $dispute->getMorphClass(),
        'supportable_id' => $dispute->id,
        'category' => SupportCaseCategory::Dispute,
        'priority' => SupportCasePriority::High,
    ]);
    $payoutSupportCase = SupportCase::factory()->create([
        'supportable_type' => $payout->getMorphClass(),
        'supportable_id' => $payout->id,
    ]);
    $bookingSupportCase = SupportCase::factory()->create([
        'supportable_type' => $booking->getMorphClass(),
        'supportable_id' => $booking->id,
    ]);
    $ledgerEntry = WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'source_type' => $payout->getMorphClass(),
        'source_id' => $payout->id,
        'type' => WalletLedgerEntryType::PayoutDebit,
        'direction' => WalletLedgerDirection::Debit,
    ]);
    $snapshot = ReportSnapshot::factory()->create([
        'generated_by' => $context['superAdmin']->id,
        'scope' => ReportSnapshotScope::LocalGovernment,
        'scopeable_type' => $context['profile']->localGovernment()->firstOrFail()->getMorphClass(),
        'scopeable_id' => $context['profile']->local_government_id,
    ]);
    $paidPayout = Payout::factory()->paid()->create([
        'artisan_profile_id' => $context['profile']->id,
        'payout_account_id' => $payoutAccount->id,
        'wallet_id' => $wallet->id,
    ]);
    $criticalDispute = Dispute::factory()->resolved($context['superAdmin'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'booking_id' => $booking->id,
    ]);
    $globalSnapshot = ReportSnapshot::factory()->create();

    expect(ReviewStatus::cases())->toHaveCount(3);
    expect(DisputeStatus::cases())->toHaveCount(5);
    expect(DisputeSeverity::cases())->toHaveCount(4);
    expect(PayoutStatus::cases())->toHaveCount(9);
    expect(PayoutAttemptStatus::cases())->toHaveCount(3);
    expect(SupportCaseStatus::cases())->toHaveCount(4);
    expect(SupportCasePriority::cases())->toHaveCount(4);
    expect(SupportCaseCategory::cases())->toHaveCount(6);
    expect(ReportSnapshotScope::cases())->toHaveCount(5);
    expect($booking->review()->firstOrFail()->is($review))->toBeTrue();
    expect($booking->disputes()->firstOrFail()->is($dispute))->toBeTrue();
    expect($booking->supportCases()->firstOrFail()->is($bookingSupportCase))->toBeTrue();
    expect($review->booking()->firstOrFail()->is($booking))->toBeTrue();
    expect($review->customer()->firstOrFail()->is($context['customer']))->toBeTrue();
    expect($review->artisanProfile()->firstOrFail()->is($context['profile']))->toBeTrue();
    expect($review->moderatedBy()->firstOrFail()->is($context['superAdmin']))->toBeTrue();
    expect($review->disputes()->firstOrFail()->is($dispute))->toBeTrue();
    expect($dispute->review()->firstOrFail()->is($review))->toBeTrue();
    expect($dispute->customer()->firstOrFail()->is($context['customer']))->toBeTrue();
    expect($dispute->openedBy()->firstOrFail()->is($context['customer']))->toBeTrue();
    expect($dispute->assignedTo()->firstOrFail()->is($context['localGovernmentAdmin']))->toBeTrue();
    expect($criticalDispute->resolvedBy()->firstOrFail()->is($context['superAdmin']))->toBeTrue();
    expect($dispute->supportCases()->firstOrFail()->is($supportCase))->toBeTrue();
    expect($supportCase->requester()->firstOrFail()->is($context['customer']))->toBeTrue();
    expect($supportCase->owner()->firstOrFail()->is($context['superAdmin']))->toBeTrue();
    expect($supportCase->supportable()->firstOrFail()->is($dispute))->toBeTrue();
    expect($context['profile']->reviews()->firstOrFail()->is($review))->toBeTrue();
    expect($context['profile']->disputes()->whereKey($dispute->id)->exists())->toBeTrue();
    expect($context['profile']->payouts()->whereKey($payout->id)->exists())->toBeTrue();
    expect($context['customer']->reviews()->firstOrFail()->is($review))->toBeTrue();
    expect($context['customer']->openedDisputes()->firstOrFail()->is($dispute))->toBeTrue();
    expect($context['localGovernmentAdmin']->assignedDisputes()->firstOrFail()->is($dispute))->toBeTrue();
    expect($context['artisan']->requestedPayouts()->firstOrFail()->is($payout))->toBeTrue();
    expect($context['superAdmin']->reportSnapshots()->whereKey($snapshot->id)->exists())->toBeTrue();
    expect($wallet->payouts()->whereKey($payout->id)->exists())->toBeTrue();
    expect($payoutAccount->payouts()->whereKey($payout->id)->exists())->toBeTrue();
    expect($payout->artisanProfile()->firstOrFail()->is($context['profile']))->toBeTrue();
    expect($payout->payoutAccount()->firstOrFail()->is($payoutAccount))->toBeTrue();
    expect($payout->wallet()->firstOrFail()->is($wallet))->toBeTrue();
    expect($payout->requestedBy()->firstOrFail()->is($context['artisan']))->toBeTrue();
    expect($payout->approvedBy()->firstOrFail()->is($context['superAdmin']))->toBeTrue();
    expect($paidPayout->status)->toBe(PayoutStatus::Paid);
    expect($payout->attempts()->firstOrFail()->is($attempt))->toBeTrue();
    expect($payout->ledgerEntries()->firstOrFail()->is($ledgerEntry))->toBeTrue();
    expect($payout->supportCases()->firstOrFail()->is($payoutSupportCase))->toBeTrue();
    expect($attempt->payout()->firstOrFail()->is($payout))->toBeTrue();
    expect($snapshot->generatedBy()->firstOrFail()->is($context['superAdmin']))->toBeTrue();
    expect($snapshot->scopeable()->firstOrFail())->toBeInstanceOf(LocalGovernment::class);
    expect($globalSnapshot->scopeable()->first())->toBeNull();
});

test('verified reviews require the booking customer completed payment release and stay one per booking', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    $unpaidBooking = Booking::factory()->finished()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    $withoutCredit = Booking::factory()->confirmed()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    $stranger = User::factory()->create();
    $action = app(SubmitVerifiedReview::class);

    expect(fn () => $action->handle($booking, $context['customer'], 6))
        ->toThrow(InvalidArgumentException::class, 'Review rating must be between one and five.');
    expect(fn () => $action->handle($booking, $stranger, 5))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->handle($unpaidBooking, $context['customer'], 5))
        ->toThrow(InvalidArgumentException::class, 'Only settled paid bookings can be reviewed.');
    expect(fn () => $action->handle($withoutCredit, $context['customer'], 5))
        ->toThrow(InvalidArgumentException::class, 'Only settled paid bookings can be reviewed.');

    $review = $action->handle($booking, $context['customer'], 5, 'Excellent repair.');

    expect($review->status)->toBe(ReviewStatus::Published);
    expect($review->artisan_profile_id)->toBe($context['profile']->id);
    expect(fn () => $action->handle($booking, $context['customer'], 4))
        ->toThrow(InvalidArgumentException::class, 'This booking has already been reviewed.');
});

test('disputes can be opened with evidence escalated resolved audited and tied to support cases', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    $review = app(SubmitVerifiedReview::class)->handle($booking, $context['customer'], 4, 'Good but late.');
    $stranger = User::factory()->create();
    $openDispute = app(OpenDispute::class);

    expect(fn () => $openDispute->handle($booking, $context['customer'], ''))
        ->toThrow(InvalidArgumentException::class, 'A dispute subject is required.');
    expect(fn () => $openDispute->handle($booking, $stranger, 'Unauthorized'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $openDispute->handle($booking, $context['customer'], 'Wrong review', review: Review::factory()->create()))
        ->toThrow(InvalidArgumentException::class, 'The selected review does not belong to this booking.');

    $dispute = $openDispute->handle(
        booking: $booking,
        actor: $context['customer'],
        subject: 'Invoice amount is disputed',
        description: 'The final invoice differs from the quote.',
        severity: DisputeSeverity::Critical,
        review: $review,
        evidence: [UploadedFile::fake()->image('receipt.jpg')],
    );
    $low = $openDispute->handle(phaseSevenConfirmedBooking($context), $context['artisan'], 'Low concern', severity: DisputeSeverity::Low);
    $medium = $openDispute->handle(phaseSevenConfirmedBooking($context), $context['localGovernmentAdmin'], 'Medium concern');
    $high = $openDispute->handle(phaseSevenConfirmedBooking($context), $context['customer'], 'High concern', severity: DisputeSeverity::High);

    expect($dispute->status)->toBe(DisputeStatus::Open);
    expect($dispute->getMedia(Dispute::EVIDENCE_COLLECTION))->toHaveCount(1);
    expect($review->refresh()->status)->toBe(ReviewStatus::Disputed);
    expect($dispute->supportCases()->firstOrFail()->priority)->toBe(SupportCasePriority::Urgent);
    expect($low->supportCases()->firstOrFail()->priority)->toBe(SupportCasePriority::Low);
    expect($medium->supportCases()->firstOrFail()->priority)->toBe(SupportCasePriority::Normal);
    expect($high->supportCases()->firstOrFail()->priority)->toBe(SupportCasePriority::High);
    expect(AuditLog::query()->where('action', 'dispute.opened')->count())->toBe(4);

    $escalateDispute = app(EscalateDispute::class);
    expect(fn () => $escalateDispute->handle($dispute, $context['customer'], 'Need state review.'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $escalateDispute->handle($dispute, $context['localGovernmentAdmin'], ''))
        ->toThrow(InvalidArgumentException::class, 'An escalation reason is required.');

    $escalated = $escalateDispute->handle($dispute, $context['localGovernmentAdmin'], 'Needs state intervention.');
    $low->forceFill(['severity' => DisputeSeverity::Critical])->save();
    $criticalEscalated = $escalateDispute->handle($low, $context['localGovernmentAdmin'], 'Critical safety concern.');
    $resolvedFactoryDispute = Dispute::factory()->resolved($context['superAdmin'])->create([
        'artisan_profile_id' => $context['profile']->id,
    ]);

    expect($escalated->status)->toBe(DisputeStatus::EscalatedToState);
    expect($escalated->severity)->toBe(DisputeSeverity::Critical);
    expect($criticalEscalated->severity)->toBe(DisputeSeverity::Critical);
    expect(fn () => $escalateDispute->handle($resolvedFactoryDispute, $context['localGovernmentAdmin'], 'Late escalation.'))
        ->toThrow(InvalidArgumentException::class, 'Only open disputes can be escalated.');

    $resolveDispute = app(ResolveDispute::class);
    expect(fn () => $resolveDispute->handle($escalated, $context['customer'], 'Resolved.'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $resolveDispute->handle($escalated, $context['superAdmin'], ''))
        ->toThrow(InvalidArgumentException::class, 'A dispute resolution is required.');

    $resolved = $resolveDispute->handle($escalated, $context['superAdmin'], 'Refund adjustment approved.', hideReview: true);

    expect($resolved->status)->toBe(DisputeStatus::Resolved);
    expect($resolved->supportCases()->firstOrFail()->status)->toBe(SupportCaseStatus::Resolved);
    expect($review->refresh()->status)->toBe(ReviewStatus::Hidden);
    expect(AuditLog::query()->where('action', 'dispute.resolved')->exists())->toBeTrue();
    expect(fn () => $resolveDispute->handle($resolved, $context['superAdmin'], 'Again.'))
        ->toThrow(InvalidArgumentException::class, 'This dispute is already resolved.');
});

test('payout requests approvals attempts and immutable wallet debits are enforced', function () {
    $context = phaseSevenContext();
    phaseSevenConfirmedBooking($context);
    $wallet = $context['profile']->wallet()->firstOrFail();
    $payoutAccount = PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $context['profile']->id]);
    $pendingAccount = PayoutAccount::factory()->create(['artisan_profile_id' => $context['profile']->id]);
    $otherAccount = PayoutAccount::factory()->verified()->create();
    $stranger = User::factory()->create();
    $requestPayout = app(RequestPayout::class);

    expect(fn () => $requestPayout->handle($context['profile'], $payoutAccount, $stranger, 100000))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $requestPayout->handle($context['profile'], $payoutAccount, $context['artisan'], 0))
        ->toThrow(InvalidArgumentException::class, 'Payout amount must be greater than zero.');
    expect(fn () => $requestPayout->handle($context['profile'], $pendingAccount, $context['artisan'], 100000))
        ->toThrow(InvalidArgumentException::class, 'A verified payout account is required.');
    expect(fn () => $requestPayout->handle($context['profile'], $otherAccount, $context['artisan'], 100000))
        ->toThrow(InvalidArgumentException::class, 'A verified payout account is required.');
    expect(fn () => $requestPayout->handle($context['profile'], $payoutAccount, $context['artisan'], 999999999))
        ->toThrow(InvalidArgumentException::class, 'Wallet balance is insufficient for this payout.');

    $payout = $requestPayout->handle($context['profile'], $payoutAccount, $context['artisan'], 500000, ['notes' => 'First payout']);

    expect($payout->status)->toBe(PayoutStatus::Pending);
    expect($payout->wallet_id)->toBe($wallet->id);

    $approvePayout = app(ApprovePayout::class);
    expect(fn () => $approvePayout->handle($payout, $context['localGovernmentAdmin']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $approvePayout->handle(Payout::factory()->paid()->create([
        'artisan_profile_id' => $context['profile']->id,
        'payout_account_id' => $payoutAccount->id,
        'wallet_id' => $wallet->id,
    ]), $context['superAdmin']))
        ->toThrow(InvalidArgumentException::class, 'Only pending payouts can be approved.');
    $wallet->forceFill(['available_balance' => 1])->save();
    expect(fn () => $approvePayout->handle($payout, $context['superAdmin']))
        ->toThrow(InvalidArgumentException::class, 'Wallet balance is insufficient for this payout.');
    $wallet->forceFill(['available_balance' => 2500000])->save();

    $approved = $approvePayout->handle($payout, $context['superAdmin']);

    expect($approved->status)->toBe(PayoutStatus::Approved);
    expect($approved->ledgerEntries()->where('type', WalletLedgerEntryType::PayoutDebit)->count())->toBe(1);
    expect($approved->wallet()->firstOrFail()->available_balance)->toBe(2000000);

    $alreadyDebited = Payout::factory()->create([
        'artisan_profile_id' => $context['profile']->id,
        'payout_account_id' => $payoutAccount->id,
        'wallet_id' => $wallet->id,
        'requested_by' => $context['artisan']->id,
        'amount' => 100000,
    ]);
    WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'source_type' => $alreadyDebited->getMorphClass(),
        'source_id' => $alreadyDebited->id,
        'type' => WalletLedgerEntryType::PayoutDebit,
        'direction' => WalletLedgerDirection::Debit,
    ]);
    $approvePayout->handle($alreadyDebited, $context['superAdmin']);
    expect($alreadyDebited->ledgerEntries()->where('type', WalletLedgerEntryType::PayoutDebit)->count())->toBe(1);

    $processPayout = app(ProcessPayout::class);
    expect(fn () => $processPayout->handle($approved, $context['localGovernmentAdmin']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $processPayout->handle(Payout::factory()->create([
        'artisan_profile_id' => $context['profile']->id,
        'payout_account_id' => $payoutAccount->id,
        'wallet_id' => $wallet->id,
    ]), $context['superAdmin']))
        ->toThrow(InvalidArgumentException::class, 'Only approved or retrying payouts can be processed.');

    $retrying = $processPayout->handle($approved, $context['superAdmin'], successful: false, failureReason: 'Bank network unavailable.', maxAttempts: 3);
    $paid = $processPayout->handle($retrying, $context['superAdmin'], successful: true, providerReference: 'transfer-phase-seven', providerPayload: ['provider' => 'manual']);
    $failedPayout = $requestPayout->handle($context['profile'], $payoutAccount, $context['artisan'], 100000);
    $failedPayout = $approvePayout->handle($failedPayout, $context['superAdmin']);
    $failedPayout = $processPayout->handle($failedPayout, $context['superAdmin'], successful: false, failureReason: 'Attempt one.', maxAttempts: 2);
    $failedPayout = $processPayout->handle($failedPayout, $context['superAdmin'], successful: false, failureReason: 'Attempt two.', maxAttempts: 2);
    $undebitedApproved = Payout::factory()->approved($context['superAdmin'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'payout_account_id' => $payoutAccount->id,
        'wallet_id' => $wallet->id,
        'requested_by' => $context['artisan']->id,
        'amount' => 100000,
    ]);
    $undebitedFailed = $processPayout->handle($undebitedApproved, $context['superAdmin'], successful: false, failureReason: 'No debit exists.', maxAttempts: 1);

    expect($retrying->status)->toBe(PayoutStatus::Retrying);
    expect($paid->status)->toBe(PayoutStatus::Paid);
    expect($paid->attempts()->count())->toBe(2);
    expect($paid->attempts()->latest('attempt_number')->firstOrFail()->status)->toBe(PayoutAttemptStatus::Successful);
    expect($failedPayout->status)->toBe(PayoutStatus::Failed);
    expect($failedPayout->attempts()->count())->toBe(2);
    expect($failedPayout->wallet()->firstOrFail()->available_balance)->toBe(2000000);
    expect($failedPayout->ledgerEntries()
        ->where('type', WalletLedgerEntryType::AdjustmentCredit)
        ->where('immutable_reference', 'payout-'.$failedPayout->id.'-failed-release')
        ->count())->toBe(1);
    expect($undebitedFailed->status)->toBe(PayoutStatus::Failed);
    expect($undebitedFailed->ledgerEntries()->where('type', WalletLedgerEntryType::AdjustmentCredit)->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'payout.paid')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'payout.failed_attempt')->count())->toBe(4);
});

test('scoped reports and document rendering summarize only visible records', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    app(SubmitVerifiedReview::class)->handle($booking, $context['customer'], 5);
    $dispute = app(OpenDispute::class)->handle($booking, $context['customer'], 'Reportable dispute');
    $payoutAccount = PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $context['profile']->id]);
    $payout = app(RequestPayout::class)->handle($context['profile'], $payoutAccount, $context['artisan'], 100000);
    app(ApprovePayout::class)->handle($payout, $context['superAdmin']);
    app(ProcessPayout::class)->handle($payout, $context['superAdmin'], successful: true, providerReference: 'report-paid');
    $outsideLocalGovernment = LocalGovernment::factory()->create([
        'state_id' => $context['profile']->state_id,
        'name' => 'Outside Phase Seven',
    ]);
    $outsideCategory = ServiceCategory::factory()->create();
    $outsideProfile = ArtisanProfile::factory()->create([
        'country_id' => $context['profile']->country_id,
        'state_id' => $context['profile']->state_id,
        'local_government_id' => $outsideLocalGovernment->id,
    ]);
    $outsideService = ArtisanService::factory()->create([
        'artisan_profile_id' => $outsideProfile->id,
        'service_category_id' => $outsideCategory->id,
    ]);
    phaseSevenConfirmedBooking($context, [
        'profile' => $outsideProfile,
        'service' => $outsideService,
        'customer' => $context['customer'],
    ]);
    $reportAction = app(GenerateScopedReport::class);

    expect(fn () => $reportAction->handle($context['artisan']))
        ->toThrow(AuthorizationException::class, 'You cannot generate scoped reports.');

    $globalReport = $reportAction->handle($context['superAdmin'], now()->subDay(), now()->addDay());
    $stateReport = $reportAction->handle($context['stateCoordinator']);
    $localGovernmentReport = $reportAction->handle($context['localGovernmentAdmin']);
    $areaReport = $reportAction->handle($context['areaAgent']);
    $fallbackAreaReporter = User::factory()->create();
    $fallbackAreaReporter->givePermissionTo(PlatformPermission::ViewAreaReports->value);
    $fallbackAreaReport = $reportAction->handle($fallbackAreaReporter);
    $renderer = app(DocumentRenderer::class);
    Pdf::fake();
    $renderedDocument = app(RenderDocument::class)->handle(
        'documents.report',
        ['snapshot' => $localGovernmentReport],
        Storage::path('reports/local-government.pdf'),
    );

    expect($globalReport->scope)->toBe(ReportSnapshotScope::Global);
    expect($globalReport->metrics['artisan_profiles'])->toBeGreaterThanOrEqual(2);
    expect($stateReport->scope)->toBe(ReportSnapshotScope::State);
    expect($stateReport->metrics['bookings_total'])->toBeGreaterThanOrEqual(2);
    expect($localGovernmentReport->scope)->toBe(ReportSnapshotScope::LocalGovernment);
    expect($localGovernmentReport->metrics['bookings_total'])->toBe(1);
    expect($localGovernmentReport->metrics['confirmed_bookings'])->toBe(1);
    expect($localGovernmentReport->metrics['published_reviews'])->toBe(1);
    expect($localGovernmentReport->metrics['open_disputes'])->toBe(1);
    expect($localGovernmentReport->metrics['open_support_cases'])->toBe(1);
    expect($localGovernmentReport->metrics['paid_payouts'])->toBe(1);
    expect($areaReport->scope)->toBe(ReportSnapshotScope::AreaAgent);
    expect($fallbackAreaReport->scope)->toBe(ReportSnapshotScope::AreaAgent);
    expect($renderer)->toBeInstanceOf(LaravelPdfDocumentRenderer::class);
    expect($renderedDocument)->toBeInstanceOf(RenderedDocument::class);
    expect($renderedDocument->view)->toBe('documents.report');
    Pdf::assertViewIs('documents.report');
    Pdf::assertViewHas('snapshot', $localGovernmentReport);
    Pdf::assertSaved(Storage::path('reports/local-government.pdf'));
    expect($dispute->supportCases()->count())->toBe(1);
});

test('customer and artisan inertia contracts expose review dispute and payout flows', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    $payoutAccount = PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $context['profile']->id]);

    $this->actingAs($context['customer'])
        ->get(route('customer.bookings.show', ['booking' => $booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/BookingShow')
            ->where('booking.id', $booking->id)
            ->where('booking.canReview', true));

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.reviews.store', ['booking' => $booking]), [
            'rating' => 5,
            'comment' => 'Clean and professional.',
        ])
        ->assertRedirect(route('customer.bookings.show', ['booking' => $booking]));
    $review = $booking->review()->firstOrFail();

    $this->actingAs($context['customer'])
        ->get(route('customer.bookings.disputes.create', ['booking' => $booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customer/DisputeCreate')
            ->where('booking.id', $booking->id)
            ->where('review.id', $review->id));

    $otherBooking = Booking::factory()->confirmed()->forCustomer($context['customer'])->create([
        'artisan_profile_id' => $context['profile']->id,
        'artisan_service_id' => $context['service']->id,
        'service_category_id' => $context['service']->service_category_id,
    ]);
    $otherReview = Review::factory()->create([
        'booking_id' => $otherBooking->id,
        'customer_id' => $context['customer']->id,
        'artisan_profile_id' => $context['profile']->id,
    ]);
    $this->actingAs($context['customer'])
        ->from(route('customer.bookings.disputes.create', ['booking' => $booking]))
        ->post(route('customer.bookings.disputes.store', ['booking' => $booking]), [
            'subject' => 'Wrong review id',
            'severity' => DisputeSeverity::Medium->value,
            'review_id' => $otherReview->id,
        ])
        ->assertRedirect(route('customer.bookings.disputes.create', ['booking' => $booking]))
        ->assertSessionHasErrors('review_id');
    expect($otherReview->refresh()->status)->toBe(ReviewStatus::Published);

    $this->actingAs($context['customer'])
        ->post(route('customer.bookings.disputes.store', ['booking' => $booking]), [
            'subject' => 'Please review the invoice',
            'description' => 'I need help reconciling the quote.',
            'severity' => DisputeSeverity::Medium->value,
            'review_id' => $review->id,
            'evidence' => [UploadedFile::fake()->image('invoice.png')],
        ])
        ->assertRedirect(route('customer.bookings.show', ['booking' => $booking]));

    $this->actingAs($context['artisan'])
        ->get(route('artisan.wallet.show', ['current_team' => $context['team']->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Wallet')
            ->where('wallet.availableBalance', 2202500)
            ->where('payoutAccounts.0.id', $payoutAccount->id)
            ->where('payouts', []));

    $this->actingAs($context['artisan'])
        ->post(route('artisan.wallet.payouts.store', ['current_team' => $context['team']->slug]), [
            'payout_account_id' => $payoutAccount->id,
            'amount' => 1000,
            'notes' => 'Phase seven payout.',
        ])
        ->assertRedirect(route('artisan.wallet.show', ['current_team' => $context['team']->slug]));

    $this->actingAs($context['artisan'])
        ->get(route('artisan.wallet.show', ['current_team' => $context['team']->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/Wallet')
            ->where('payouts.0.amount', 100000)
            ->where('payouts.0.status', PayoutStatus::Pending->value));

    $artisanBooking = phaseSevenConfirmedBooking($context);
    $this->actingAs($context['artisan'])
        ->get(route('artisan.bookings.disputes.create', [
            'current_team' => $context['team']->slug,
            'booking' => $artisanBooking,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('artisan/DisputeCreate')
            ->where('booking.id', $artisanBooking->id));

    $this->actingAs($context['artisan'])
        ->post(route('artisan.bookings.disputes.store', [
            'current_team' => $context['team']->slug,
            'booking' => $artisanBooking,
        ]), [
            'subject' => 'Customer unreachable',
            'description' => 'Need operational support.',
            'severity' => DisputeSeverity::Low->value,
        ])
        ->assertRedirect(route('artisan.bookings.index', ['current_team' => $context['team']->slug]));

    expect(Dispute::query()->count())->toBe(2);
    expect(Payout::query()->where('requested_by', $context['artisan']->id)->exists())->toBeTrue();
});

test('phase seven filament resources policies and table actions are scoped', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    $dispute = app(OpenDispute::class)->handle($booking, $context['customer'], 'Filament dispute');
    $payoutAccount = PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $context['profile']->id]);
    $payout = app(RequestPayout::class)->handle($context['profile'], $payoutAccount, $context['artisan'], 150000);
    $report = app(GenerateScopedReport::class)->handle($context['superAdmin']);
    $stranger = User::factory()->create();
    $disputePolicy = new DisputePolicy;
    $payoutPolicy = new PayoutPolicy;
    $reportPolicy = new ReportSnapshotPolicy;

    $this->actingAs($context['superAdmin']);
    expect(DisputeResource::canAccess())->toBeTrue();
    expect(PayoutResource::canAccess())->toBeTrue();
    expect(ReportSnapshotResource::canAccess())->toBeTrue();
    expect(DisputeResource::canCreate())->toBeFalse();
    expect(PayoutResource::canCreate())->toBeFalse();
    expect(ReportSnapshotResource::canCreate())->toBeFalse();
    expect(DisputeResource::getRelations())->toBe([]);
    expect(PayoutResource::getRelations())->toBe([]);
    expect(ReportSnapshotResource::getRelations())->toBe([]);
    expect(array_keys(DisputeResource::getPages()))->toBe(['index', 'view']);
    expect(array_keys(PayoutResource::getPages()))->toBe(['index', 'view']);
    expect(array_keys(ReportSnapshotResource::getPages()))->toBe(['index', 'view']);
    expect(DisputeResource::infolist(Schema::make())->getComponents())->toHaveCount(10);
    expect(PayoutResource::infolist(Schema::make())->getComponents())->toHaveCount(10);
    expect(ReportSnapshotResource::infolist(Schema::make())->getComponents())->toHaveCount(6);
    expect(DisputeResource::getEloquentQuery()->whereKey($dispute->id)->exists())->toBeTrue();
    expect(PayoutResource::getEloquentQuery()->whereKey($payout->id)->exists())->toBeTrue();
    expect(ReportSnapshotResource::getEloquentQuery()->whereKey($report->id)->exists())->toBeTrue();

    $this->actingAs($context['localGovernmentAdmin']);
    expect(DisputeResource::getEloquentQuery()->whereKey($dispute->id)->exists())->toBeTrue();
    expect(PayoutResource::canAccess())->toBeFalse();
    expect(PayoutResource::getEloquentQuery()->whereKey($payout->id)->exists())->toBeTrue();
    expect(ReportSnapshotResource::getEloquentQuery()->whereKey($report->id)->exists())->toBeFalse();
    $this->actingAs($stranger);
    expect(DisputeResource::canAccess())->toBeFalse();
    expect(PayoutResource::canAccess())->toBeFalse();
    expect(ReportSnapshotResource::canAccess())->toBeFalse();
    expect(DisputeResource::getEloquentQuery()->count())->toBe(0);
    expect(PayoutResource::getEloquentQuery()->count())->toBe(0);
    expect(ReportSnapshotResource::getEloquentQuery()->count())->toBe(0);
    auth()->logout();
    expect(DisputeResource::getEloquentQuery()->count())->toBe(0);
    expect(PayoutResource::getEloquentQuery()->count())->toBe(0);
    expect(ReportSnapshotResource::getEloquentQuery()->count())->toBe(0);

    expect($disputePolicy->viewAny($context['localGovernmentAdmin']))->toBeTrue();
    expect($disputePolicy->view($context['localGovernmentAdmin'], $dispute))->toBeTrue();
    expect($disputePolicy->create($context['localGovernmentAdmin']))->toBeTrue();
    expect($disputePolicy->update($context['localGovernmentAdmin'], $dispute))->toBeTrue();
    expect($disputePolicy->delete($context['localGovernmentAdmin'], $dispute))->toBeFalse();
    expect($disputePolicy->restore($context['localGovernmentAdmin'], $dispute))->toBeFalse();
    expect($disputePolicy->forceDelete($context['localGovernmentAdmin'], $dispute))->toBeFalse();
    expect($payoutPolicy->viewAny($context['superAdmin']))->toBeTrue();
    expect($payoutPolicy->view($context['superAdmin'], $payout))->toBeTrue();
    expect($payoutPolicy->create($context['superAdmin']))->toBeFalse();
    expect($payoutPolicy->update($context['superAdmin'], $payout))->toBeTrue();
    expect($payoutPolicy->delete($context['superAdmin'], $payout))->toBeFalse();
    expect($payoutPolicy->restore($context['superAdmin'], $payout))->toBeFalse();
    expect($payoutPolicy->forceDelete($context['superAdmin'], $payout))->toBeFalse();
    expect($reportPolicy->viewAny($context['stateCoordinator']))->toBeTrue();
    expect($reportPolicy->view($context['superAdmin'], $report))->toBeTrue();
    expect($reportPolicy->view($context['stateCoordinator'], $report))->toBeFalse();
    expect($reportPolicy->create($context['stateCoordinator']))->toBeTrue();
    expect($reportPolicy->update($context['superAdmin'], $report))->toBeFalse();
    expect($reportPolicy->delete($context['superAdmin'], $report))->toBeFalse();
    expect($reportPolicy->restore($context['superAdmin'], $report))->toBeFalse();
    expect($reportPolicy->forceDelete($context['superAdmin'], $report))->toBeFalse();
    expect(Gate::forUser($context['superAdmin'])->allows('update', $payout))->toBeTrue();

    phaseSevenLivewire($context['superAdmin'], ListDisputes::class)
        ->assertCanSeeTableRecords([$dispute])
        ->callTableAction('escalate', phaseSevenRecordKey($dispute), [
            'reason' => 'Escalate from Filament.',
        ])
        ->assertHasNoTableActionErrors();
    phaseSevenLivewire($context['superAdmin'], ListDisputes::class)
        ->callTableAction('resolve', phaseSevenRecordKey($dispute), [
            'resolution' => 'Resolved from Filament.',
            'hide_review' => false,
        ])
        ->assertHasNoTableActionErrors();
    phaseSevenLivewire($context['superAdmin'], ViewDispute::class, ['record' => phaseSevenRecordKey($dispute->refresh())])
        ->assertOk();

    phaseSevenLivewire($context['superAdmin'], ListPayouts::class)
        ->assertCanSeeTableRecords([$payout])
        ->callTableAction('approve', phaseSevenRecordKey($payout))
        ->assertHasNoTableActionErrors();
    phaseSevenLivewire($context['superAdmin'], ListPayouts::class)
        ->callTableAction('recordFailure', phaseSevenRecordKey($payout), [
            'failure_reason' => 'Bank timeout.',
        ])
        ->assertHasNoTableActionErrors();
    phaseSevenLivewire($context['superAdmin'], ListPayouts::class)
        ->callTableAction('process', phaseSevenRecordKey($payout->refresh()))
        ->assertHasNoTableActionErrors();
    phaseSevenLivewire($context['superAdmin'], ViewPayout::class, ['record' => phaseSevenRecordKey($payout->refresh())])
        ->assertOk();

    Pdf::fake();
    phaseSevenLivewire($context['superAdmin'], ListReportSnapshots::class)
        ->assertCanSeeTableRecords([$report])
        ->callTableAction('generateReport')
        ->assertHasNoTableActionErrors()
        ->callTableAction('renderDocument', phaseSevenRecordKey($report))
        ->assertHasNoTableActionErrors();
    phaseSevenLivewire($context['superAdmin'], ViewReportSnapshot::class, ['record' => phaseSevenRecordKey($report)])
        ->assertOk();
    Pdf::assertViewIs('documents.report');
});

test('filament table action closures validate unsupported states directly', function () {
    $context = phaseSevenContext();
    $booking = phaseSevenConfirmedBooking($context);
    $dispute = app(OpenDispute::class)->handle($booking, $context['customer'], 'Direct table dispute');
    $payoutAccount = PayoutAccount::factory()->verified()->create(['artisan_profile_id' => $context['profile']->id]);
    $payout = app(RequestPayout::class)->handle($context['profile'], $payoutAccount, $context['artisan'], 100000);
    $this->actingAs($context['superAdmin']);

    $escalateActionMethod = new ReflectionMethod(DisputesTable::class, 'escalateAction');
    $escalateActionMethod->setAccessible(true);
    $escalateAction = $escalateActionMethod->invoke(null);
    assert($escalateAction instanceof Action);
    $escalateActionFunction = $escalateAction->getActionFunction();
    assert($escalateActionFunction instanceof Closure);
    $dispute->forceFill(['status' => DisputeStatus::Resolved])->save();
    expect(fn () => $escalateActionFunction($dispute, [
        'reason' => 'Too late.',
    ]))->toThrow(InvalidArgumentException::class);

    $approveActionMethod = new ReflectionMethod(PayoutsTable::class, 'approveAction');
    $approveActionMethod->setAccessible(true);
    $approveAction = $approveActionMethod->invoke(null);
    assert($approveAction instanceof Action);
    $approveActionFunction = $approveAction->getActionFunction();
    assert($approveActionFunction instanceof Closure);
    $payout->forceFill(['status' => PayoutStatus::Paid])->save();
    expect(fn () => $approveActionFunction($payout))
        ->toThrow(InvalidArgumentException::class);
});
