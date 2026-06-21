<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Enums\PlatformPermission;
use App\Filament\Resources\WaitlistEntries\Pages\ListWaitlistEntries;
use App\Filament\Resources\WaitlistEntries\Pages\ViewWaitlistEntry;
use App\Filament\Resources\WaitlistEntries\Schemas\WaitlistEntryInfolist;
use App\Filament\Resources\WaitlistEntries\Tables\WaitlistEntriesTable;
use App\Models\User;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Waitlist';

    protected static ?string $modelLabel = 'Waitlist entry';

    protected static ?string $pluralModelLabel = 'Waitlist entries';

    public static function infolist(Schema $schema): Schema
    {
        return WaitlistEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaitlistEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<WaitlistEntry>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<WaitlistEntry> $query */
        $query = parent::getEloquentQuery()
            ->with(['country', 'localGovernment', 'serviceCategory', 'state', 'territory']);

        if (! $user instanceof User || ! $user->can(PlatformPermission::ViewWaitlistEntries->value)) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can(PlatformPermission::ViewWaitlistEntries->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitlistEntries::route('/'),
            'view' => ViewWaitlistEntry::route('/{record}'),
        ];
    }
}
