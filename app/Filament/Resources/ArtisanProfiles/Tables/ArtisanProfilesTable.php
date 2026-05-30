<?php

namespace App\Filament\Resources\ArtisanProfiles\Tables;

use App\Actions\Operations\SuspendArtisanProfile;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PlatformPermission;
use App\Enums\ReasonCodeCategory;
use App\Models\ArtisanProfile;
use App\Models\ReasonCode;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ArtisanProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_name')
                    ->label('Business')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('verification_status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('subscription_status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('availability_status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('localGovernment.name')
                    ->label('LGA')
                    ->searchable(),
                TextColumn::make('territory.name')
                    ->label('Territory')
                    ->searchable(),
                TextColumn::make('onboardedByAgent.name')
                    ->label('Onboarded by')
                    ->searchable(),
                TextColumn::make('suspensionReasonCode.label')
                    ->label('Suspension reason')
                    ->searchable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('suspended_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_public')
                    ->boolean(),
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
                SelectFilter::make('verification_status')
                    ->options(ArtisanVerificationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::suspendAction(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->color('danger')
            ->schema([
                Select::make('reason_code_id')
                    ->label('Reason')
                    ->options(fn (): array => ReasonCode::query()
                        ->forCategory(ReasonCodeCategory::Suspension)
                        ->active()
                        ->orderBy('label')
                        ->pluck('label', 'id')
                        ->all())
                    ->required(),
                Textarea::make('reason')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->visible(fn (ArtisanProfile $record): bool => self::canSuspend($record))
            ->requiresConfirmation()
            ->action(function (ArtisanProfile $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                $reasonCode = ReasonCode::query()->findOrFail(self::integerFormValue($data, 'reason_code_id'));
                $reason = isset($data['reason']) && is_string($data['reason'])
                    ? $data['reason']
                    : null;

                app(SuspendArtisanProfile::class)->handle(
                    profile: $record,
                    actor: $actor,
                    reasonCode: $reasonCode,
                    reason: $reason,
                );
            });
    }

    private static function canSuspend(ArtisanProfile $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record->verification_status !== ArtisanVerificationStatus::Suspended
            && $user->can(PlatformPermission::ModerateArtisanProfiles->value)
            && Gate::forUser($user)->allows('update', $record);
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
}
