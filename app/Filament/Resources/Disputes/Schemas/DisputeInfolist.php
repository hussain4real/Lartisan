<?php

namespace App\Filament\Resources\Disputes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DisputeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('status')->badge(),
                TextEntry::make('severity')->badge(),
                TextEntry::make('artisanProfile.business_name')->label('Artisan'),
                TextEntry::make('booking.tracker_code')->label('Booking'),
                TextEntry::make('openedBy.name')->label('Opened by'),
                TextEntry::make('description')->placeholder('-')->columnSpanFull(),
                TextEntry::make('resolution')->placeholder('-')->columnSpanFull(),
                TextEntry::make('opened_at')->dateTime(),
                TextEntry::make('resolved_at')->dateTime()->placeholder('-'),
            ]);
    }
}
