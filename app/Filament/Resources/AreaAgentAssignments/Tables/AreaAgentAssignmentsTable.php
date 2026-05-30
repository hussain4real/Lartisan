<?php

namespace App\Filament\Resources\AreaAgentAssignments\Tables;

use App\Models\AreaAgentAssignment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AreaAgentAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Area agent')
                    ->searchable(),
                TextColumn::make('territory.name')
                    ->searchable(),
                TextColumn::make('territory.localGovernment.name')
                    ->label('LGA')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('active')
                    ->state(fn (AreaAgentAssignment $record): bool => $record->ends_at === null)
                    ->boolean(),
                TextColumn::make('assignedBy.name')
                    ->label('Assigned by')
                    ->searchable(),
                TextColumn::make('reasonCode.label')
                    ->label('Reason code')
                    ->searchable(),
                TextColumn::make('reason')
                    ->searchable(),
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
