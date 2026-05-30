<?php

namespace App\Filament\Resources\KycSubmissions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class KycSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('artisanProfile.id')
                    ->label('Artisan profile'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('risk_level')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('submitted_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('reviewed_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('reviewed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('decision_reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('notes')
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
