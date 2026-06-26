<?php

namespace App\Filament\Resources\Disputes\Tables;

use App\Actions\Disputes\EscalateDispute;
use App\Actions\Disputes\ResolveDispute;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\DisputeTargetType;
use App\Enums\WalletLedgerDirection;
use App\Models\Dispute;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class DisputesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('severity')->badge()->searchable(),
                TextColumn::make('target')->badge()->searchable(),
                TextColumn::make('artisanProfile.business_name')
                    ->label('Artisan')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('openedBy.name')
                    ->label('Opened by')
                    ->searchable(),
                TextColumn::make('opened_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(DisputeStatus::class),
                SelectFilter::make('severity')->options(DisputeSeverity::class),
                SelectFilter::make('target')->options(DisputeTargetType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::escalateAction(),
                self::resolveAction(),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    private static function escalateAction(): Action
    {
        return Action::make('escalate')
            ->schema([
                Textarea::make('reason')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (Dispute $record): bool => self::canUpdate($record)
                && in_array($record->status, [DisputeStatus::Open, DisputeStatus::UnderLocalGovernmentReview], true))
            ->action(function (Dispute $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(EscalateDispute::class)->handle(
                    dispute: $record,
                    actor: $actor,
                    reason: is_string($data['reason'] ?? null) ? $data['reason'] : '',
                );
            });
    }

    private static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->schema([
                Textarea::make('resolution')
                    ->required()
                    ->maxLength(2000),
                Toggle::make('hide_review')
                    ->label('Hide linked review'),
                TextInput::make('money_adjustment_amount')
                    ->label('Money adjustment amount')
                    ->numeric()
                    ->minValue(1),
                Select::make('money_adjustment_direction')
                    ->label('Money adjustment direction')
                    ->options(WalletLedgerDirection::class)
                    ->default(WalletLedgerDirection::Debit->value)
                    ->visible(fn (Get $get): bool => filled($get('money_adjustment_amount'))),
            ])
            ->visible(fn (Dispute $record): bool => self::canUpdate($record)
                && ! in_array($record->status, [DisputeStatus::Resolved, DisputeStatus::Closed], true))
            ->action(function (Dispute $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ResolveDispute::class)->handle(
                    dispute: $record,
                    actor: $actor,
                    resolution: is_string($data['resolution'] ?? null) ? $data['resolution'] : '',
                    hideReview: (bool) ($data['hide_review'] ?? false),
                    moneyAdjustmentAmount: is_numeric($data['money_adjustment_amount'] ?? null)
                        ? (int) $data['money_adjustment_amount']
                        : null,
                    moneyAdjustmentDirection: WalletLedgerDirection::from(
                        is_string($data['money_adjustment_direction'] ?? null)
                            ? $data['money_adjustment_direction']
                            : WalletLedgerDirection::Debit->value,
                    ),
                );
            });
    }

    private static function canUpdate(Dispute $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('update', $record);
    }
}
