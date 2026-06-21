<?php

namespace App\Filament\Resources\WaitlistEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WaitlistEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('audience_type')
                    ->badge(),
                TextEntry::make('business_name')
                    ->placeholder('-'),
                TextEntry::make('serviceCategory.name')
                    ->label('Category')
                    ->placeholder('-'),
                TextEntry::make('country.name')
                    ->label('Country')
                    ->placeholder('-'),
                TextEntry::make('state.name')
                    ->label('State')
                    ->placeholder('-'),
                TextEntry::make('localGovernment.name')
                    ->label('Local government')
                    ->placeholder('-'),
                TextEntry::make('territory.name')
                    ->label('Territory')
                    ->placeholder('-'),
                IconEntry::make('contact_consent')
                    ->label('Consent')
                    ->boolean(),
                TextEntry::make('note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
