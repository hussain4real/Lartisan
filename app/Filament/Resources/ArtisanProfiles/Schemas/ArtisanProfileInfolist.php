<?php

namespace App\Filament\Resources\ArtisanProfiles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ArtisanProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('team.name')
                    ->label('Team'),
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('business_name'),
                TextEntry::make('verification_status')
                    ->badge(),
                TextEntry::make('subscription_status')
                    ->badge(),
                TextEntry::make('availability_status')
                    ->badge(),
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
                TextEntry::make('onboardedByAgent.name')
                    ->label('Onboarded by agent')
                    ->placeholder('-'),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                IconEntry::make('is_public')
                    ->boolean(),
                TextEntry::make('internal_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('public_summary')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('years_experience')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('service_radius_km')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('public_phone')
                    ->placeholder('-'),
                TextEntry::make('public_email')
                    ->placeholder('-'),
            ]);
    }
}
