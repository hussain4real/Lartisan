<?php

namespace App\Filament\Resources\SupportCases\Schemas;

use App\Models\SupportCase;
use App\Models\SupportCaseNote;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportCaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('status')->badge(),
                TextEntry::make('priority')->badge(),
                TextEntry::make('category')->badge(),
                TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                TextEntry::make('requester.name')->label('Requester')->placeholder('-'),
                TextEntry::make('supportable_type')->label('Linked record')->placeholder('-'),
                TextEntry::make('description')->placeholder('-')->columnSpanFull(),
                TextEntry::make('resolution_notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('internal_notes')
                    ->label('Internal notes')
                    ->state(fn (SupportCase $record): string => $record->notes()
                        ->with('author')
                        ->latest('id')
                        ->get()
                        ->map(function (SupportCaseNote $note): string {
                            $author = $note->author;

                            return sprintf(
                                '%s: %s',
                                $author instanceof User ? $author->name : 'Unknown',
                                $note->body,
                            );
                        })
                        ->join("\n\n"))
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('opened_at')->dateTime(),
                TextEntry::make('resolved_at')->dateTime()->placeholder('-'),
                TextEntry::make('closed_at')->dateTime()->placeholder('-'),
            ]);
    }
}
