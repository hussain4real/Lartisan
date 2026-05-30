<?php

namespace App\Filament\Resources\AreaAgentAssignments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AreaAgentAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('territory.name')
                    ->label('Territory'),
                TextEntry::make('starts_at')
                    ->dateTime(),
                TextEntry::make('ends_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('assigned_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('reason')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
