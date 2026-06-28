<?php

namespace App\Filament\Resources\SupportCases;

use App\Enums\PlatformPermission;
use App\Filament\Resources\SupportCases\Pages\ListSupportCases;
use App\Filament\Resources\SupportCases\Pages\ViewSupportCase;
use App\Filament\Resources\SupportCases\Schemas\SupportCaseInfolist;
use App\Filament\Resources\SupportCases\Tables\SupportCasesTable;
use App\Models\SupportCase;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportCaseResource extends Resource
{
    protected static ?string $model = SupportCase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Support inbox';

    public static function infolist(Schema $schema): Schema
    {
        return SupportCaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<SupportCase>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<SupportCase> $query */
        $query = parent::getEloquentQuery()
            ->with(['requester', 'owner', 'notes.author', 'supportable']);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $query->visibleTo($user);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(PlatformPermission::ManageSupportCases->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportCases::route('/'),
            'view' => ViewSupportCase::route('/{record}'),
        ];
    }
}
