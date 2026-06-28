<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('rating'),
                TextEntry::make('status')->badge(),
                TextEntry::make('moderation_signal')->label('Signal')->placeholder('-'),
                TextEntry::make('moderation_score')->label('Score'),
                TextEntry::make('artisanProfile.business_name')->label('Artisan'),
                TextEntry::make('booking.tracker_code')->label('Booking'),
                TextEntry::make('customer.name')->label('Customer')->placeholder('Guest'),
                TextEntry::make('comment')->placeholder('-')->columnSpanFull(),
                TextEntry::make('artisan_response')->placeholder('-')->columnSpanFull(),
                TextEntry::make('proof_count')
                    ->label('Private proof files')
                    ->state(fn (Review $record): int => $record->getMedia(Review::PROOF_COLLECTION)->count()),
                TextEntry::make('moderation_notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('reviewed_at')->dateTime(),
                TextEntry::make('moderated_at')->dateTime()->placeholder('-'),
            ]);
    }
}
