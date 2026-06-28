<?php

namespace App\Filament\Resources\SupportCases\Tables;

use App\Actions\SupportCases\AddSupportCaseNote;
use App\Actions\SupportCases\AssignSupportCase;
use App\Actions\SupportCases\UpdateSupportCaseStatus;
use App\Enums\PlatformPermission;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Models\SupportCase;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SupportCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('priority')->badge()->searchable(),
                TextColumn::make('category')->badge()->searchable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->label('Requester')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('opened_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SupportCaseStatus::class),
                SelectFilter::make('priority')->options(SupportCasePriority::class),
                SelectFilter::make('category')->options(SupportCaseCategory::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::assignAction(),
                self::updateStatusAction(),
                self::addInternalNoteAction(),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    private static function assignAction(): Action
    {
        return Action::make('assign')
            ->schema([
                Select::make('owner_id')
                    ->label('Owner')
                    ->options(fn (): array => User::permission(PlatformPermission::ManageSupportCases->value)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [
                            $user->id => "{$user->name} ({$user->email})",
                        ])
                        ->all())
                    ->required()
                    ->searchable(),
            ])
            ->fillForm(fn (SupportCase $record): array => ['owner_id' => $record->owner_id])
            ->visible(fn (SupportCase $record): bool => self::canUpdate($record))
            ->action(function (SupportCase $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                $ownerId = $data['owner_id'] ?? null;

                if (! is_int($ownerId) && ! is_string($ownerId)) {
                    throw new InvalidArgumentException('A support owner is required.');
                }

                $owner = User::query()->findOrFail($ownerId);

                app(AssignSupportCase::class)->handle($record, $actor, $owner);
            });
    }

    private static function updateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Update status')
            ->schema([
                Select::make('status')
                    ->options(SupportCaseStatus::class)
                    ->required(),
                Textarea::make('resolution_notes')
                    ->label('Resolution notes')
                    ->maxLength(2000),
            ])
            ->fillForm(fn (SupportCase $record): array => [
                'status' => $record->status->value,
                'resolution_notes' => $record->resolution_notes,
            ])
            ->visible(fn (SupportCase $record): bool => self::canUpdate($record))
            ->action(function (SupportCase $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                $statusValue = $data['status'] ?? SupportCaseStatus::InProgress;

                if ($statusValue instanceof SupportCaseStatus) {
                    $status = $statusValue;
                } elseif (is_string($statusValue)) {
                    $status = SupportCaseStatus::from($statusValue);
                } else {
                    throw new InvalidArgumentException('A valid support case status is required.');
                }

                $resolution = is_string($data['resolution_notes'] ?? null) ? $data['resolution_notes'] : null;

                app(UpdateSupportCaseStatus::class)->handle($record, $actor, $status, $resolution);
            });
    }

    private static function addInternalNoteAction(): Action
    {
        return Action::make('addInternalNote')
            ->label('Add note')
            ->schema([
                Textarea::make('body')
                    ->label('Internal note')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (SupportCase $record): bool => self::canUpdate($record))
            ->action(function (SupportCase $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(AddSupportCaseNote::class)->handle(
                    supportCase: $record,
                    author: $actor,
                    body: is_string($data['body'] ?? null) ? $data['body'] : '',
                );
            });
    }

    private static function canUpdate(SupportCase $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('update', $record);
    }
}
