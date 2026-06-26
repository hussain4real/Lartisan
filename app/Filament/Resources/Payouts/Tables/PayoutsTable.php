<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Actions\Payouts\ApprovePayout;
use App\Actions\Payouts\DispatchPayoutTransfer;
use App\Actions\Payouts\ProcessPayout;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Models\Payout;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
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
                TextColumn::make('provider_status')
                    ->label('Provider')
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('NGN', divideBy: 100)
                    ->sortable(),
                TextColumn::make('batch.id')
                    ->label('Batch')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('attempts_count')
                    ->label('Attempts')
                    ->counts('attempts')
                    ->sortable(),
                TextColumn::make('payoutAccount.bank_name')
                    ->label('Bank')
                    ->searchable(),
                TextColumn::make('provider_transfer_code')
                    ->label('Transfer')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('requestedBy.name')
                    ->label('Requested by')
                    ->searchable(),
                TextColumn::make('requested_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PayoutStatus::class),
                SelectFilter::make('provider_status')
                    ->options([
                        'dispatching' => 'Dispatching',
                        'pending' => 'Pending',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'reversed' => 'Reversed',
                        'uncertain' => 'Uncertain',
                        'action_required' => 'Action required',
                    ]),
                Filter::make('exceptions')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                        $query
                            ->whereIn('status', [
                                PayoutStatus::Failed,
                                PayoutStatus::InReview,
                                PayoutStatus::Retrying,
                            ])
                            ->orWhere('provider_status', 'uncertain');
                    })),
            ])
            ->headerActions([
                self::dispatchBatchAction(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::dispatchAction(),
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
            ->label('Manual paid')
            ->icon(Heroicon::CheckCircle)
            ->schema([
                Textarea::make('reason')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (Payout $record): bool => self::canUpdate($record)
                && in_array($record->status, [PayoutStatus::Approved, PayoutStatus::InReview, PayoutStatus::Retrying], true))
            ->action(function (Payout $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ProcessPayout::class)->handle(
                    payout: $record,
                    processor: $actor,
                    successful: true,
                    providerReference: 'manual-'.$record->id.'-'.now()->timestamp,
                    providerPayload: [
                        'manual_reason' => is_string($data['reason'] ?? null) ? $data['reason'] : null,
                        'source' => 'filament',
                    ],
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
                && in_array($record->status, [PayoutStatus::Approved, PayoutStatus::InReview, PayoutStatus::Processing, PayoutStatus::Retrying], true))
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

    private static function dispatchAction(): Action
    {
        return Action::make('dispatch')
            ->label('Dispatch')
            ->icon(Heroicon::PaperAirplane)
            ->requiresConfirmation()
            ->visible(fn (Payout $record): bool => self::canUpdate($record)
                && in_array($record->status, [PayoutStatus::Approved, PayoutStatus::Retrying], true))
            ->action(function (Payout $record): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(DispatchPayoutTransfer::class)->handle($record, $actor);
            });
    }

    private static function dispatchBatchAction(): Action
    {
        return Action::make('dispatchBatch')
            ->label('Dispatch batch')
            ->icon(Heroicon::ArrowPath)
            ->requiresConfirmation()
            ->visible(fn (): bool => auth()->user() instanceof User
                && auth()->user()->can(PlatformPermission::ManagePayouts->value))
            ->action(function (): void {
                Artisan::call('payouts:dispatch-approved', ['--no-interaction' => true]);
            });
    }

    private static function canUpdate(Payout $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('update', $record);
    }
}
