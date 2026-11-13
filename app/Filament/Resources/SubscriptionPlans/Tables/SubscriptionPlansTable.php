<?php

namespace App\Filament\Resources\SubscriptionPlans\Tables;

use App\Enums\SubscriptionInterval;
use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('interval')
                    ->badge(),
                TextColumn::make('price_amount')
                    ->label('Price')
                    ->state(fn (SubscriptionPlan $record): string => sprintf(
                        '%s %s',
                        $record->currency_code,
                        number_format($record->price_amount / 100, 2),
                    )),
                TextColumn::make('duration_days')
                    ->label('Days')
                    ->sortable(),
                IconColumn::make('includes_team_management')
                    ->label('Team mgmt')
                    ->boolean(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('subscriptions_count')
                    ->label('Subscriptions')
                    ->counts('subscriptions'),
            ])
            ->filters([
                SelectFilter::make('interval')
                    ->options(SubscriptionInterval::class),
            ])
            ->recordActions([
                Action::make('toggleActive')
                    ->label(fn (SubscriptionPlan $record): string => $record->active ? 'Deactivate' : 'Activate')
                    ->icon(fn (SubscriptionPlan $record): Heroicon => $record->active ? Heroicon::OutlinedPauseCircle : Heroicon::OutlinedCheckCircle)
                    ->action(function (SubscriptionPlan $record): void {
                        $before = SubscriptionPlanResource::auditPayload($record);
                        $record->update(['active' => ! $record->active]);
                        $fresh = $record->refresh();

                        SubscriptionPlanResource::recordAudit(
                            $fresh->active ? 'subscription_plan.activated' : 'subscription_plan.deactivated',
                            $fresh,
                            $before,
                            SubscriptionPlanResource::auditPayload($fresh),
                        );
                    }),
                EditAction::make(),
            ]);
    }
}
