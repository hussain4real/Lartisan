<?php

namespace App\Filament\Resources\Territories;

use App\Enums\PlatformPermission;
use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\Territories\Schemas\TerritoryForm;
use App\Filament\Resources\Territories\Tables\TerritoriesTable;
use App\Models\Territory;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TerritoryResource extends Resource
{
    protected static ?string $model = Territory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Geography';

    public static function form(Schema $schema): Schema
    {
        return TerritoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TerritoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<Territory>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Territory> $query */
        $query = parent::getEloquentQuery()
            ->with('localGovernment.state')
            ->orderBy('name');

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $query->visibleTo($user);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can(PlatformPermission::ManageTerritories->value);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerritories::route('/'),
            'create' => CreateTerritory::route('/create'),
            'edit' => EditTerritory::route('/{record}/edit'),
        ];
    }
}
