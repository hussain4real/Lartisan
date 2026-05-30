<?php

namespace App\Filament\Resources\ReasonCodes\Tables;

use App\Enums\ReasonCodeCategory;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReasonCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->badge()
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(ReasonCodeCategory::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
