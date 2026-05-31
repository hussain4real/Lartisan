<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Actions\Payouts\ApprovePayout;
use App\Actions\Payouts\ProcessPayout;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('artisanProfile.business_name')
                    ->label('Artisan')
                    ->searchable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('amount')
                    ->money('NGN', divideBy: 100)
                    ->sortable(),
                TextColumn::make('payoutAccount.bank_name')
                    ->label('Bank')
                    ->searchable(),
                TextColumn::make('requestedBy.name')
                    ->label('Requested by')
                    ->searchable(),
                TextColumn::make('requested_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PayoutStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::processAction(),
                self::failAttemptAction(),
            ])
            ->defaultSort('requested_at', 'desc');
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->visible(fn (Payout $record): bool => self::canUpdate($record)
                && in_array($record->status, [PayoutStatus::Pending, PayoutStatus::InReview], true))
            ->action(function (Payout $record): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ApprovePayout::class)->handle($record, $actor);
            });
    }

    private static function processAction(): Action
    {
        return Action::make('process')
            ->label('Mark paid')
            ->visible(fn (Payout $record): bool => self::canUpdate($record)
                && in_array($record->status, [PayoutStatus::Approved, PayoutStatus::Retrying], true))
            ->action(function (Payout $record): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ProcessPayout::class)->handle(
                    payout: $record,
                    processor: $actor,
                    successful: true,
                    providerReference: 'manual-'.$record->id.'-'.now()->timestamp,
                    providerPayload: ['source' => 'filament'],
                );
            });
    }

    private static function failAttemptAction(): Action
    {
        return Action::make('recordFailure')
            ->label('Record failure')
            ->schema([
                Textarea::make('failure_reason')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (Payout $record): bool => self::canUpdate($record)
                && in_array($record->status, [PayoutStatus::Approved, PayoutStatus::Retrying], true))
            ->action(function (Payout $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ProcessPayout::class)->handle(
                    payout: $record,
                    processor: $actor,
                    successful: false,
                    failureReason: is_string($data['failure_reason'] ?? null) ? $data['failure_reason'] : null,
                    providerPayload: ['source' => 'filament'],
                );
            });
    }

    private static function canUpdate(Payout $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('update', $record);
    }
}
