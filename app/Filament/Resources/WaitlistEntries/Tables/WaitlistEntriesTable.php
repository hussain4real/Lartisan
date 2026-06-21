<?php

namespace App\Filament\Resources\WaitlistEntries\Tables;

use App\Enums\WaitlistAudienceType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WaitlistEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('audience_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('business_name')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('serviceCategory.name')
                    ->label('Category')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable(),
                TextColumn::make('localGovernment.name')
                    ->label('LGA')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('territory.name')
                    ->placeholder('-')
                    ->searchable(),
                IconColumn::make('contact_consent')
                    ->label('Consent')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('audience_type')
                    ->options(WaitlistAudienceType::class),
                SelectFilter::make('service_category_id')
                    ->label('Category')
                    ->relationship('serviceCategory', 'name'),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
