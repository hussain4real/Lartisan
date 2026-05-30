<?php

namespace App\Filament\Resources\KycSubmissions\Tables;

use App\Actions\Artisans\RecordFieldVisit;
use App\Actions\Operations\ApproveKyc;
use App\Actions\Operations\EscalateKyc;
use App\Actions\Operations\RejectKyc;
use App\Actions\Operations\ReturnKyc;
use App\Actions\Operations\ReviewKyc;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\FieldVisitStatus;
use App\Enums\KycRiskLevel;
use App\Enums\PlatformPermission;
use App\Enums\ReasonCodeCategory;
use App\Models\FieldVisit;
use App\Models\KycSubmission;
use App\Models\ReasonCode;
use App\Models\Territory;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class KycSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('artisanProfile.business_name')
                    ->label('Business')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('risk_level')
                    ->badge()
                    ->searchable(),
                TextColumn::make('artisanProfile.localGovernment.name')
                    ->label('LGA')
                    ->searchable(),
                TextColumn::make('artisanProfile.territory.name')
                    ->label('Territory')
                    ->searchable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewedBy.name')
                    ->label('Reviewer')
                    ->searchable(),
                TextColumn::make('reasonCode.label')
                    ->label('Reason')
                    ->searchable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ArtisanVerificationStatus::class),
                SelectFilter::make('risk_level')
                    ->options(KycRiskLevel::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::fieldVisitAction(),
                self::reviewAction(),
                self::approveAction(),
                self::returnAction(),
                self::rejectAction(),
                self::escalateAction(),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    private static function reviewAction(): Action
    {
        return self::decisionAction('review', 'Mark in review', PlatformPermission::ReviewStandardKyc);
    }

    private static function fieldVisitAction(): Action
    {
        return Action::make('recordFieldVisit')
            ->label('Record visit')
            ->schema([
                Select::make('status')
                    ->options(FieldVisitStatus::class)
                    ->default(FieldVisitStatus::Completed->value)
                    ->required(),
                Select::make('territory_id')
                    ->label('Territory')
                    ->options(fn (): array => Territory::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                DateTimePicker::make('visited_at'),
                Textarea::make('notes')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->visible(fn (KycSubmission $record): bool => self::canRecordFieldVisit($record))
            ->action(function (KycSubmission $record, array $data): void {
                /** @var User $areaAgent */
                $areaAgent = auth()->user();
                $territory = array_key_exists('territory_id', $data) && filled($data['territory_id'])
                    ? Territory::query()->findOrFail(self::integerFormValue($data, 'territory_id'))
                    : null;
                $visitedAt = $data['visited_at'] ?? null;
                $visitedAt = $visitedAt instanceof DateTimeInterface
                    ? $visitedAt
                    : (is_string($visitedAt) && $visitedAt !== '' ? Date::parse($visitedAt) : null);
                $status = $data['status'] instanceof FieldVisitStatus
                    ? $data['status']
                    : FieldVisitStatus::from(self::stringFormValue($data, 'status'));
                $notes = isset($data['notes']) && is_string($data['notes'])
                    ? $data['notes']
                    : null;

                app(RecordFieldVisit::class)->handle(
                    profile: $record->artisanProfile()->firstOrFail(),
                    areaAgent: $areaAgent,
                    submission: $record,
                    territory: $territory,
                    status: $status,
                    visitedAt: $visitedAt,
                    notes: $notes,
                );
            });
    }

    private static function approveAction(): Action
    {
        return self::decisionAction('approve', 'Approve', PlatformPermission::ReviewStandardKyc);
    }

    private static function returnAction(): Action
    {
        return self::decisionAction('return', 'Return', PlatformPermission::ReviewStandardKyc);
    }

    private static function rejectAction(): Action
    {
        return self::decisionAction('reject', 'Reject', PlatformPermission::ReviewStandardKyc);
    }

    private static function escalateAction(): Action
    {
        return self::decisionAction('escalate', 'Escalate', PlatformPermission::ReviewStandardKyc);
    }

    private static function decisionAction(string $name, string $label, PlatformPermission $permission): Action
    {
        return Action::make($name)
            ->label($label)
            ->schema([
                Select::make('reason_code_id')
                    ->label('Reason')
                    ->options(fn (): array => ReasonCode::query()
                        ->forCategory(ReasonCodeCategory::KycDecision)
                        ->active()
                        ->orderBy('label')
                        ->pluck('label', 'id')
                        ->all())
                    ->required(),
                Select::make('risk_level')
                    ->options(KycRiskLevel::class),
                Textarea::make('notes')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->visible(fn (KycSubmission $record): bool => self::canRunDecision($record, $permission))
            ->action(function (KycSubmission $record, array $data) use ($name): void {
                /** @var User $reviewer */
                $reviewer = auth()->user();
                $reasonCode = ReasonCode::query()->findOrFail(self::integerFormValue($data, 'reason_code_id'));
                $riskLevel = array_key_exists('risk_level', $data) && filled($data['risk_level'])
                    ? KycRiskLevel::from(self::stringFormValue($data, 'risk_level'))
                    : null;
                $notes = isset($data['notes']) && is_string($data['notes'])
                    ? $data['notes']
                    : null;

                match ($name) {
                    'review' => app(ReviewKyc::class)->handle($record, $reviewer, $reasonCode, $notes, $riskLevel),
                    'approve' => app(ApproveKyc::class)->handle($record, $reviewer, $reasonCode, $notes, $riskLevel),
                    'return' => app(ReturnKyc::class)->handle($record, $reviewer, $reasonCode, $notes, $riskLevel),
                    'reject' => app(RejectKyc::class)->handle($record, $reviewer, $reasonCode, $notes, $riskLevel),
                    'escalate' => app(EscalateKyc::class)->handle($record, $reviewer, $reasonCode, $notes, $riskLevel),
                    default => throw new InvalidArgumentException('Unsupported KYC action.'),
                };
            });
    }

    private static function canRunDecision(KycSubmission $record, PlatformPermission $permission): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can($permission->value)
            && Gate::forUser($user)->allows('update', $record)
            && ! in_array($record->status, [
                ArtisanVerificationStatus::Approved,
                ArtisanVerificationStatus::Rejected,
                ArtisanVerificationStatus::Returned,
                ArtisanVerificationStatus::Suspended,
            ], true);
    }

    private static function canRecordFieldVisit(KycSubmission $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can(PlatformPermission::SubmitFieldKyc->value)
            && Gate::forUser($user)->allows('create', [
                FieldVisit::class,
                $record->artisanProfile()->firstOrFail(),
            ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function integerFormValue(array $data, string $key): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);

        throw_if($value === false, InvalidArgumentException::class, "Invalid {$key} value.");

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function stringFormValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        throw_unless(is_string($value), InvalidArgumentException::class, "Invalid {$key} value.");

        return $value;
    }
}
