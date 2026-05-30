<?php

use App\Actions\Operations\ApproveKyc;
use App\Actions\Operations\AssignTerritory;
use App\Actions\Operations\EscalateKyc;
use App\Actions\Operations\RejectKyc;
use App\Actions\Operations\ReturnKyc;
use App\Actions\Operations\ReviewKyc;
use App\Actions\Operations\SuspendArtisanProfile;
use App\Enums\AdminProfileStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\FieldVisitStatus;
use App\Enums\KycRiskLevel;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\ReasonCodeCategory;
use App\Filament\Resources\AreaAgentAssignments\AreaAgentAssignmentResource;
use App\Filament\Resources\AreaAgentAssignments\Pages\ListAreaAgentAssignments;
use App\Filament\Resources\ArtisanProfiles\ArtisanProfileResource;
use App\Filament\Resources\ArtisanProfiles\Pages\ListArtisanProfiles;
use App\Filament\Resources\KycSubmissions\KycSubmissionResource;
use App\Filament\Resources\KycSubmissions\Pages\ListKycSubmissions;
use App\Filament\Resources\KycSubmissions\Tables\KycSubmissionsTable;
use App\Filament\Resources\ReasonCodes\Pages\CreateReasonCode;
use App\Filament\Resources\ReasonCodes\Pages\EditReasonCode;
use App\Filament\Resources\ReasonCodes\Pages\ListReasonCodes;
use App\Filament\Resources\ReasonCodes\ReasonCodeResource;
use App\Models\AdminProfile;
use App\Models\AreaAgentAssignment;
use App\Models\ArtisanProfile;
use App\Models\AuditLog;
use App\Models\KycSubmission;
use App\Models\LocalGovernment;
use App\Models\ReasonCode;
use App\Models\State;
use App\Models\StatusHistory;
use App\Models\Territory;
use App\Models\User;
use App\Policies\AreaAgentAssignmentPolicy;
use App\Policies\ReasonCodePolicy;
use Database\Seeders\PilotUserSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PilotUserSeeder::class);
});

/**
 * @return array{
 *     superAdmin: User,
 *     stateCoordinator: User,
 *     localGovernmentAdmin: User,
 *     areaAgent: User,
 *     artisan: User,
 *     customer: User
 * }
 */
function phaseFourUsers(): array
{
    return [
        'superAdmin' => User::query()->where('email', 'super.admin@lartisan.test')->firstOrFail(),
        'stateCoordinator' => User::query()->where('email', 'state.coordinator@lartisan.test')->firstOrFail(),
        'localGovernmentAdmin' => User::query()->where('email', 'lga.admin@lartisan.test')->firstOrFail(),
        'areaAgent' => User::query()->where('email', 'area.agent@lartisan.test')->firstOrFail(),
        'artisan' => User::query()->where('email', 'artisan@lartisan.test')->firstOrFail(),
        'customer' => User::query()->where('email', 'customer@lartisan.test')->firstOrFail(),
    ];
}

function phaseFourReason(ReasonCodeCategory $category, string $code): ReasonCode
{
    return ReasonCode::query()
        ->where('category', $category)
        ->where('code', $code)
        ->firstOrFail();
}

function phaseFourProfileFor(LocalGovernment $localGovernment, ?Territory $territory = null): ArtisanProfile
{
    $state = $localGovernment->state()->firstOrFail();
    $country = $state->country()->firstOrFail();

    return ArtisanProfile::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'local_government_id' => $localGovernment->id,
        'territory_id' => $territory?->id,
        'verification_status' => ArtisanVerificationStatus::Submitted,
    ]);
}

function phaseFourUsePanel(string $panel): void
{
    Filament::setCurrentPanel($panel);
}

function phaseFourRecordKey(Model $record): string
{
    $key = $record->getKey();

    assert(is_int($key) || is_string($key));

    return (string) $key;
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  class-string<TComponent>  $component
 * @param  array<string, mixed>  $params
 * @return Testable<TComponent>
 */
function phaseFourLivewire(User $user, string $component, array $params = []): Testable
{
    Livewire::actingAs($user);

    /** @var Testable<TComponent> $testable */
    $testable = Livewire::test($component, $params);

    return $testable;
}

test('phase four reason codes seed and operation panels enforce role access', function () {
    $users = phaseFourUsers();

    expect(ReasonCode::query()->count())->toBe(8);
    expect(ReasonCode::query()->forCategory(ReasonCodeCategory::KycDecision)->active()->count())->toBe(4);
    expect(ReasonCode::query()->forCategory(ReasonCodeCategory::TerritoryAssignment)->active()->count())->toBe(2);
    expect(ReasonCode::query()->forCategory(ReasonCodeCategory::Suspension)->active()->count())->toBe(2);
    expect(AreaAgentAssignment::query()->whereNotNull('reason_code_id')->count())->toBe(2);

    expect($users['superAdmin']->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
    expect($users['stateCoordinator']->canAccessPanel(Panel::make()->id('state')))->toBeTrue();
    expect($users['localGovernmentAdmin']->canAccessPanel(Panel::make()->id('lga')))->toBeTrue();
    expect($users['areaAgent']->canAccessPanel(Panel::make()->id('agent')))->toBeTrue();
    expect($users['customer']->canAccessPanel(Panel::make()->id('unknown')))->toBeFalse();

    $this->actingAs($users['customer'])->get('/agent/kyc-submissions')->assertForbidden();
});

test('filament operation resources expose scoped queues only', function () {
    $users = phaseFourUsers();
    $pilotProfile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();
    $pilotSubmission = $pilotProfile->kycSubmissions()->firstOrFail();
    $fct = State::query()->where('slug', 'federal-capital-territory')->firstOrFail();
    $lagos = State::query()->where('slug', 'lagos')->firstOrFail();
    $otherFctLocalGovernment = LocalGovernment::query()
        ->where('state_id', $fct->id)
        ->where('slug', '!=', 'abuja-municipal-area-council')
        ->firstOrFail();
    $lagosLocalGovernment = LocalGovernment::factory()->create(['state_id' => $lagos->id]);
    $otherFctProfile = phaseFourProfileFor($otherFctLocalGovernment);
    $outsideStateProfile = phaseFourProfileFor($lagosLocalGovernment);

    $otherFctSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => $otherFctProfile->id,
    ]);
    KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => $outsideStateProfile->id,
    ]);

    $this->actingAs($users['superAdmin']);
    expect(KycSubmissionResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotSubmission->id, $otherFctSubmission->id, KycSubmission::query()->latest('id')->firstOrFail()->id]);
    expect(ArtisanProfileResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id, $otherFctProfile->id, $outsideStateProfile->id]);
    expect(AreaAgentAssignmentResource::getEloquentQuery()->count())->toBe(2);
    expect(ReasonCodeResource::canAccess())->toBeTrue();
    $this->actingAs($users['stateCoordinator']);
    expect(KycSubmissionResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotSubmission->id, $otherFctSubmission->id]);
    expect(ArtisanProfileResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$pilotProfile->id, $otherFctProfile->id]);
    expect(AreaAgentAssignmentResource::getEloquentQuery()->count())->toBe(2);
    $this->actingAs($users['localGovernmentAdmin']);
    expect(KycSubmissionResource::getEloquentQuery()->pluck('id')->all())->toBe([$pilotSubmission->id]);
    expect(ArtisanProfileResource::getEloquentQuery()->pluck('id')->all())->toBe([$pilotProfile->id]);
    expect(AreaAgentAssignmentResource::getEloquentQuery()->count())->toBe(2);
    expect(ReasonCodeResource::canAccess())->toBeFalse();
    $this->get('/lga/reason-codes')->assertForbidden();

    $this->actingAs($users['areaAgent']);
    expect(KycSubmissionResource::getEloquentQuery()->pluck('id')->all())->toBe([$pilotSubmission->id]);
    expect(ArtisanProfileResource::getEloquentQuery()->pluck('id')->all())->toBe([$pilotProfile->id]);
    expect(AreaAgentAssignmentResource::getEloquentQuery()->count())->toBe(2);
    $this->actingAs($users['customer']);
    expect(KycSubmissionResource::getEloquentQuery()->count())->toBe(0);
    expect(ArtisanProfileResource::getEloquentQuery()->count())->toBe(0);
    expect(AreaAgentAssignmentResource::getEloquentQuery()->count())->toBe(0);

    auth()->logout();
    expect(KycSubmissionResource::getEloquentQuery()->count())->toBe(0);
    expect(ArtisanProfileResource::getEloquentQuery()->count())->toBe(0);
    expect(AreaAgentAssignmentResource::getEloquentQuery()->count())->toBe(0);
    expect(ReasonCodeResource::canAccess())->toBeFalse();
});

test('filament operation resource configuration is wired', function () {
    expect(KycSubmissionResource::infolist(Schema::make())->getComponents())->toHaveCount(10);
    expect(ArtisanProfileResource::infolist(Schema::make())->getComponents())->toHaveCount(22);
    expect(AreaAgentAssignmentResource::infolist(Schema::make())->getComponents())->toHaveCount(8);
    expect(ReasonCodeResource::form(Schema::make())->getComponents())->toHaveCount(5);

    expect(KycSubmissionResource::canCreate())->toBeFalse();
    expect(ArtisanProfileResource::canCreate())->toBeFalse();
    expect(AreaAgentAssignmentResource::canCreate())->toBeFalse();

    expect(array_keys(KycSubmissionResource::getPages()))->toBe(['index', 'view']);
    expect(array_keys(ArtisanProfileResource::getPages()))->toBe(['index', 'view']);
    expect(array_keys(AreaAgentAssignmentResource::getPages()))->toBe(['index', 'view']);
    expect(array_keys(ReasonCodeResource::getPages()))->toBe(['index', 'create', 'edit']);

    expect(KycSubmissionResource::getRelations())->toBe([]);
    expect(ArtisanProfileResource::getRelations())->toBe([]);
    expect(AreaAgentAssignmentResource::getRelations())->toBe([]);
    expect(ReasonCodeResource::getRelations())->toBe([]);
});

test('filament operation pages dispatch verification actions', function () {
    $users = phaseFourUsers();
    $amac = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $wuseMarket = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
    $documentsComplete = phaseFourReason(ReasonCodeCategory::KycDecision, 'documents-complete');
    $missingDocument = phaseFourReason(ReasonCodeCategory::KycDecision, 'missing-document');
    $fieldCheckNeeded = phaseFourReason(ReasonCodeCategory::KycDecision, 'field-check-needed');
    $identityMismatch = phaseFourReason(ReasonCodeCategory::KycDecision, 'identity-mismatch');
    $coverageBalancing = phaseFourReason(ReasonCodeCategory::TerritoryAssignment, 'coverage-balancing');
    $suspensionReason = phaseFourReason(ReasonCodeCategory::Suspension, 'verification-concern');

    $fieldVisitSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac, $wuseMarket)->id,
    ]);

    phaseFourUsePanel('agent');
    phaseFourLivewire($users['areaAgent'], ListKycSubmissions::class)
        ->assertTableActionVisible('recordFieldVisit', phaseFourRecordKey($fieldVisitSubmission))
        ->callTableAction('recordFieldVisit', phaseFourRecordKey($fieldVisitSubmission), [
            'status' => FieldVisitStatus::Completed->value,
            'territory_id' => $wuseMarket->id,
            'visited_at' => now()->toDateTimeString(),
            'notes' => 'Visited from the agent queue.',
        ])
        ->assertHasNoTableActionErrors();

    $rawFieldVisitSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac, $wuseMarket)->id,
    ]);
    $fieldVisitActionMethod = new ReflectionMethod(KycSubmissionsTable::class, 'fieldVisitAction');
    $fieldVisitActionMethod->setAccessible(true);
    $fieldVisitAction = $fieldVisitActionMethod->invoke(null);
    assert($fieldVisitAction instanceof Action);
    $fieldVisitActionFunction = $fieldVisitAction->getActionFunction();
    assert($fieldVisitActionFunction instanceof Closure);
    $fieldVisitActionFunction($rawFieldVisitSubmission, [
        'status' => FieldVisitStatus::Completed->value,
        'visited_at' => now(),
    ]);

    $unsupportedDecisionSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    $decisionActionMethod = new ReflectionMethod(KycSubmissionsTable::class, 'decisionAction');
    $decisionActionMethod->setAccessible(true);
    $unsupportedDecisionAction = $decisionActionMethod->invoke(null, 'unsupported', 'Unsupported', PlatformPermission::ReviewStandardKyc);
    assert($unsupportedDecisionAction instanceof Action);
    $unsupportedDecisionActionFunction = $unsupportedDecisionAction->getActionFunction();
    assert($unsupportedDecisionActionFunction instanceof Closure);
    $this->actingAs($users['localGovernmentAdmin']);
    expect(fn () => $unsupportedDecisionActionFunction($unsupportedDecisionSubmission, [
        'reason_code_id' => $fieldCheckNeeded->id,
    ]))->toThrow(InvalidArgumentException::class);

    $reviewSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    $approveSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    $returnSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    $rejectSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    $escalateSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);

    phaseFourUsePanel('lga');
    phaseFourLivewire($users['localGovernmentAdmin'], ListKycSubmissions::class)
        ->assertTableActionsExistInOrder(['view', 'recordFieldVisit', 'review', 'approve', 'return', 'reject', 'escalate'])
        ->assertTableActionHidden('recordFieldVisit', phaseFourRecordKey($reviewSubmission))
        ->callTableAction('review', phaseFourRecordKey($reviewSubmission), [
            'reason_code_id' => $fieldCheckNeeded->id,
            'risk_level' => KycRiskLevel::Medium->value,
            'notes' => 'Review from the Filament queue.',
        ])
        ->assertHasNoTableActionErrors();

    phaseFourLivewire($users['localGovernmentAdmin'], ListKycSubmissions::class)
        ->callTableAction('approve', phaseFourRecordKey($approveSubmission), [
            'reason_code_id' => $documentsComplete->id,
        ])
        ->assertHasNoTableActionErrors();

    phaseFourLivewire($users['localGovernmentAdmin'], ListKycSubmissions::class)
        ->callTableAction('return', phaseFourRecordKey($returnSubmission), [
            'reason_code_id' => $missingDocument->id,
            'notes' => 'Return from the Filament queue.',
        ])
        ->assertHasNoTableActionErrors();

    phaseFourLivewire($users['localGovernmentAdmin'], ListKycSubmissions::class)
        ->callTableAction('reject', phaseFourRecordKey($rejectSubmission), [
            'reason_code_id' => $identityMismatch->id,
            'notes' => 'Reject from the Filament queue.',
        ])
        ->assertHasNoTableActionErrors();

    phaseFourLivewire($users['localGovernmentAdmin'], ListKycSubmissions::class)
        ->callTableAction('escalate', phaseFourRecordKey($escalateSubmission), [
            'reason_code_id' => $fieldCheckNeeded->id,
            'notes' => 'Escalate from the Filament queue.',
        ])
        ->assertHasNoTableActionErrors();

    expect($reviewSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::LgaReview);
    expect($approveSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Approved);
    expect($returnSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Returned);
    expect($rejectSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Rejected);
    expect($escalateSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Escalated);

    $suspendProfile = phaseFourProfileFor($amac);
    phaseFourLivewire($users['localGovernmentAdmin'], ListArtisanProfiles::class)
        ->assertTableActionVisible('suspend', phaseFourRecordKey($suspendProfile))
        ->callTableAction('suspend', phaseFourRecordKey($suspendProfile), [
            'reason_code_id' => $suspensionReason->id,
            'reason' => 'Suspended from the Filament profile queue.',
        ])
        ->assertHasNoTableActionErrors();
    expect($suspendProfile->refresh()->verification_status)->toBe(ArtisanVerificationStatus::Suspended);

    $suspendWithoutReasonTextProfile = phaseFourProfileFor($amac);
    phaseFourLivewire($users['localGovernmentAdmin'], ListArtisanProfiles::class)
        ->callTableAction('suspend', phaseFourRecordKey($suspendWithoutReasonTextProfile), [
            'reason_code_id' => $suspensionReason->id,
        ])
        ->assertHasNoTableActionErrors();
    expect($suspendWithoutReasonTextProfile->refresh()->verification_status)->toBe(ArtisanVerificationStatus::Suspended);

    $newAgent = User::factory()->create(['name' => 'Filament Relief Agent']);
    $newAgent->assignRole(PlatformRole::AreaAgent->value);

    phaseFourLivewire($users['localGovernmentAdmin'], ListAreaAgentAssignments::class)
        ->assertActionExists('assignTerritory')
        ->callAction('assignTerritory', [
            'area_agent_id' => $newAgent->id,
            'territory_id' => $wuseMarket->id,
            'reason_code_id' => $coverageBalancing->id,
            'reason' => 'Assigned from the Filament territory queue.',
        ])
        ->assertHasNoActionErrors();
    expect($newAgent->areaAgentAssignments()->where('territory_id', $wuseMarket->id)->exists())->toBeTrue();

    $newAgentWithoutReasonText = User::factory()->create(['name' => 'Filament Reserve Agent']);
    $newAgentWithoutReasonText->assignRole(PlatformRole::AreaAgent->value);

    phaseFourLivewire($users['localGovernmentAdmin'], ListAreaAgentAssignments::class)
        ->callAction('assignTerritory', [
            'area_agent_id' => $newAgentWithoutReasonText->id,
            'territory_id' => $wuseMarket->id,
            'reason_code_id' => $coverageBalancing->id,
        ])
        ->assertHasNoActionErrors();
    expect($newAgentWithoutReasonText->areaAgentAssignments()->where('territory_id', $wuseMarket->id)->exists())->toBeTrue();

    phaseFourUsePanel('admin');
    $reasonCode = phaseFourReason(ReasonCodeCategory::KycDecision, 'documents-complete');
    phaseFourLivewire($users['superAdmin'], ListReasonCodes::class)
        ->assertActionExists('create')
        ->assertTableActionExists('edit', null, phaseFourRecordKey($reasonCode));

    phaseFourLivewire($users['superAdmin'], EditReasonCode::class, ['record' => $reasonCode->getRouteKey()])
        ->assertActionExists('delete')
        ->fillForm([
            'category' => ReasonCodeCategory::KycDecision->value,
            'code' => $reasonCode->code,
            'label' => 'Documents Complete',
            'description' => 'Verified from edit page.',
            'active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    phaseFourLivewire($users['superAdmin'], CreateReasonCode::class)
        ->fillForm([
            'category' => ReasonCodeCategory::KycDecision->value,
            'code' => 'supplemental-check',
            'label' => 'Supplemental Check',
            'description' => 'Created from create page.',
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(ReasonCode::query()->where('code', 'supplemental-check')->exists())->toBeTrue();
});

test('kyc decision actions write decision state status histories and audit entries', function () {
    $users = phaseFourUsers();
    $profile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();
    $submission = $profile->kycSubmissions()->firstOrFail();
    $amac = LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail();
    $documentsComplete = phaseFourReason(ReasonCodeCategory::KycDecision, 'documents-complete');
    $missingDocument = phaseFourReason(ReasonCodeCategory::KycDecision, 'missing-document');
    $fieldCheckNeeded = phaseFourReason(ReasonCodeCategory::KycDecision, 'field-check-needed');
    $identityMismatch = phaseFourReason(ReasonCodeCategory::KycDecision, 'identity-mismatch');

    $draftSubmission = KycSubmission::factory()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    app(ReviewKyc::class)->handle($draftSubmission, $users['localGovernmentAdmin'], $fieldCheckNeeded, 'Start review.');
    expect($draftSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::LgaReview);

    app(ReviewKyc::class)->handle($submission, $users['localGovernmentAdmin'], $fieldCheckNeeded, 'LGA review started.', KycRiskLevel::Medium);
    expect($submission->refresh()->status)->toBe(ArtisanVerificationStatus::LgaReview);
    expect($submission->reasonCode()->firstOrFail()->is($fieldCheckNeeded))->toBeTrue();

    app(ApproveKyc::class)->handle($submission, $users['localGovernmentAdmin'], $documentsComplete, 'Approved.');
    expect($submission->refresh()->status)->toBe(ArtisanVerificationStatus::Approved);
    expect($profile->refresh()->verification_status)->toBe(ArtisanVerificationStatus::Approved);
    expect($profile->approvedBy()->firstOrFail()->is($users['localGovernmentAdmin']))->toBeTrue();
    expect($profile->is_public)->toBeTrue();

    $returnedSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    app(ReturnKyc::class)->handle($returnedSubmission, $users['localGovernmentAdmin'], $missingDocument, 'Upload a clearer bill.');
    expect($returnedSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Returned);

    $rejectedSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
    ]);
    app(RejectKyc::class)->handle($rejectedSubmission, $users['localGovernmentAdmin'], $identityMismatch, 'Identity mismatch.');
    expect($rejectedSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Rejected);

    $escalatedSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
        'risk_level' => KycRiskLevel::High,
    ]);
    app(EscalateKyc::class)->handle($escalatedSubmission, $users['localGovernmentAdmin'], $fieldCheckNeeded, 'Needs state review.');
    expect($escalatedSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Escalated);
    app(ApproveKyc::class)->handle($escalatedSubmission, $users['stateCoordinator'], $documentsComplete, 'State approved.');
    expect($escalatedSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Approved);

    $highRiskSubmission = KycSubmission::factory()->submitted()->create([
        'artisan_profile_id' => phaseFourProfileFor($amac)->id,
        'risk_level' => KycRiskLevel::High,
    ]);
    app(ApproveKyc::class)->handle($highRiskSubmission, $users['stateCoordinator'], $documentsComplete, 'High risk approval.');
    expect($highRiskSubmission->refresh()->status)->toBe(ArtisanVerificationStatus::Approved);

    expect(fn () => app(ReturnKyc::class)->handle($submission->refresh(), $users['localGovernmentAdmin'], $missingDocument))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ApproveKyc::class)->handle($returnedSubmission->refresh(), $users['localGovernmentAdmin'], $documentsComplete))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ApproveKyc::class)->handle($highRiskSubmission->refresh(), $users['localGovernmentAdmin'], $documentsComplete))
        ->toThrow(AuthorizationException::class);

    expect(AuditLog::query()->where('action', 'kyc.lga_review')->count())->toBeGreaterThanOrEqual(2);
    expect(AuditLog::query()->where('action', 'kyc.approved')->count())->toBeGreaterThanOrEqual(3);
    expect(AuditLog::query()->where('action', 'kyc.returned')->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kyc.rejected')->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'kyc.escalated')->count())->toBe(1);
    expect(AuditLog::query()->whereNotNull('reason_code_id')->count())->toBeGreaterThanOrEqual(8);
    expect(StatusHistory::query()->where('statusable_type', (new KycSubmission)->getMorphClass())->count())->toBeGreaterThanOrEqual(8);
});

test('territory assignment and suspension actions are scoped authorized and audited', function () {
    $users = phaseFourUsers();
    $coverageBalancing = phaseFourReason(ReasonCodeCategory::TerritoryAssignment, 'coverage-balancing');
    $agentTransfer = phaseFourReason(ReasonCodeCategory::TerritoryAssignment, 'agent-transfer');
    $suspensionReason = phaseFourReason(ReasonCodeCategory::Suspension, 'verification-concern');
    $wrongReason = phaseFourReason(ReasonCodeCategory::KycDecision, 'documents-complete');
    $wuseMarket = Territory::query()->where('slug', 'wuse-market')->firstOrFail();
    $garkiMarket = Territory::query()->where('slug', 'garki-market')->firstOrFail();
    $profile = ArtisanProfile::query()->where('business_name', 'Wuse Sparks Electrical')->firstOrFail();
    $newAgent = User::factory()->create(['name' => 'Relief Agent']);
    $newAgent->assignRole(PlatformRole::AreaAgent->value);
    AdminProfile::factory()->create([
        'user_id' => $newAgent->id,
        'role' => PlatformRole::AreaAgent,
        'scope_type' => LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail()->getMorphClass(),
        'scope_id' => LocalGovernment::query()->where('slug', 'abuja-municipal-area-council')->firstOrFail()->id,
        'status' => AdminProfileStatus::Active,
    ]);

    $assignment = app(AssignTerritory::class)->handle(
        areaAgent: $newAgent,
        territory: $wuseMarket,
        actor: $users['localGovernmentAdmin'],
        reasonCode: $coverageBalancing,
        reason: 'Initial relief coverage.',
    );
    $reassignment = app(AssignTerritory::class)->handle(
        areaAgent: $newAgent,
        territory: $garkiMarket,
        actor: $users['localGovernmentAdmin'],
        reasonCode: $agentTransfer,
        reason: 'Move to Garki.',
    );
    $timedAgent = User::factory()->create(['name' => 'Timed Agent']);
    $timedAgent->assignRole(PlatformRole::AreaAgent->value);
    $startsAt = now()->subDay();
    $timedAssignment = app(AssignTerritory::class)->handle(
        areaAgent: $timedAgent,
        territory: $wuseMarket,
        actor: $users['localGovernmentAdmin'],
        reasonCode: $coverageBalancing,
        startsAt: $startsAt,
    );

    expect($assignment->refresh()->ends_at)->not->toBeNull();
    expect($reassignment->refresh()->ends_at)->toBeNull();
    expect($timedAssignment->starts_at?->toDateTimeString())->toBe($startsAt->toDateTimeString());
    expect($reassignment->reasonCode()->firstOrFail()->is($agentTransfer))->toBeTrue();
    expect(AuditLog::query()->where('action', 'territory.assigned')->count())->toBe(2);
    expect(AuditLog::query()->where('action', 'territory.reassigned')->count())->toBe(1);
    expect(fn () => app(AssignTerritory::class)->handle($users['customer'], $wuseMarket, $users['localGovernmentAdmin'], $coverageBalancing))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(AssignTerritory::class)->handle($newAgent, $wuseMarket, $users['customer'], $coverageBalancing))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AssignTerritory::class)->handle($newAgent, $wuseMarket, $users['localGovernmentAdmin'], $wrongReason))
        ->toThrow(InvalidArgumentException::class);

    app(SuspendArtisanProfile::class)->handle(
        profile: $profile,
        actor: $users['localGovernmentAdmin'],
        reasonCode: $suspensionReason,
        reason: 'Unresolved verification concern.',
    );

    expect($profile->refresh()->verification_status)->toBe(ArtisanVerificationStatus::Suspended);
    expect($profile->is_public)->toBeFalse();
    expect($profile->suspendedBy()->firstOrFail()->is($users['localGovernmentAdmin']))->toBeTrue();
    expect($profile->suspensionReasonCode()->firstOrFail()->is($suspensionReason))->toBeTrue();
    expect($users['localGovernmentAdmin']->suspendedArtisanProfiles()->whereKey($profile->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'artisan_profile.suspended')->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'artisan_profile.suspended')->firstOrFail()->reasonCode()->firstOrFail()->is($suspensionReason))->toBeTrue();
    expect(StatusHistory::query()->where('to_status', ArtisanVerificationStatus::Suspended->value)->exists())->toBeTrue();
    expect(fn () => app(SuspendArtisanProfile::class)->handle($profile->refresh(), $users['localGovernmentAdmin'], $wrongReason))
        ->toThrow(InvalidArgumentException::class);

    $assignmentPolicy = new AreaAgentAssignmentPolicy;
    expect($assignmentPolicy->viewAny($users['localGovernmentAdmin']))->toBeTrue();
    expect($assignmentPolicy->view($users['localGovernmentAdmin'], $reassignment))->toBeTrue();
    expect($assignmentPolicy->create($users['localGovernmentAdmin']))->toBeTrue();
    expect($assignmentPolicy->assign($users['superAdmin'], $wuseMarket))->toBeTrue();
    expect($assignmentPolicy->assign($users['stateCoordinator'], $wuseMarket))->toBeTrue();
    expect($assignmentPolicy->assign($users['localGovernmentAdmin'], $wuseMarket))->toBeTrue();
    expect($assignmentPolicy->assign($users['areaAgent'], $wuseMarket))->toBeFalse();
    $unscopedAssigner = User::factory()->create();
    $unscopedAssigner->assignRole(PlatformRole::LocalGovernmentAdmin->value);
    expect($assignmentPolicy->assign($unscopedAssigner, $wuseMarket))->toBeFalse();
    $unsupportedScopeAssigner = User::factory()->create();
    $unsupportedScopeAssigner->assignRole(PlatformRole::LocalGovernmentAdmin->value);
    AdminProfile::factory()->create([
        'user_id' => $unsupportedScopeAssigner->id,
        'role' => PlatformRole::LocalGovernmentAdmin,
        'scope_type' => User::class,
        'scope_id' => $unsupportedScopeAssigner->id,
        'status' => AdminProfileStatus::Active,
    ]);
    expect($assignmentPolicy->assign($unsupportedScopeAssigner, $wuseMarket))->toBeFalse();
    expect($assignmentPolicy->update($users['localGovernmentAdmin'], $reassignment))->toBeTrue();
    expect($assignmentPolicy->delete($users['localGovernmentAdmin'], $reassignment))->toBeFalse();
    expect($assignmentPolicy->restore($users['localGovernmentAdmin'], $reassignment))->toBeFalse();
    expect($assignmentPolicy->forceDelete($users['localGovernmentAdmin'], $reassignment))->toBeFalse();

    $reasonPolicy = new ReasonCodePolicy;
    expect($reasonPolicy->viewAny($users['localGovernmentAdmin']))->toBeTrue();
    expect($reasonPolicy->view($users['localGovernmentAdmin'], $suspensionReason))->toBeTrue();
    expect($reasonPolicy->create($users['superAdmin']))->toBeTrue();
    expect($reasonPolicy->update($users['superAdmin'], $suspensionReason))->toBeTrue();
    expect($reasonPolicy->delete($users['superAdmin'], $suspensionReason))->toBeTrue();
    expect($reasonPolicy->restore($users['superAdmin'], $suspensionReason))->toBeFalse();
    expect($reasonPolicy->forceDelete($users['superAdmin'], $suspensionReason))->toBeFalse();
    expect(Gate::forUser($users['localGovernmentAdmin'])->allows('update', $profile))->toBeTrue();
});
