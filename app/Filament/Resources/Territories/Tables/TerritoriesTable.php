<?php

namespace App\Filament\Resources\Territories\Tables;

use App\Enums\TerritoryType;
use App\Filament\Resources\LocalGovernments\LocalGovernmentResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TerritoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('localGovernment.name')
                    ->label('LGA')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('localGovernment.state.name')
                    ->label('State')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('active')
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
                SelectFilter::make('type')
                    ->options(TerritoryType::class),
                SelectFilter::make('local_government_id')
                    ->label('LGA')
                    ->relationship('localGovernment', 'name', fn (Builder $query): Builder => $query
                        ->whereIn('id', LocalGovernmentResource::getEloquentQuery()->select('id')))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
