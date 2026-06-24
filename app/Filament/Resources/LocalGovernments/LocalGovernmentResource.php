<?php

namespace App\Filament\Resources\LocalGovernments;

use App\Enums\PlatformPermission;
use App\Filament\Resources\LocalGovernments\Pages\CreateLocalGovernment;
use App\Filament\Resources\LocalGovernments\Pages\EditLocalGovernment;
use App\Filament\Resources\LocalGovernments\Pages\ListLocalGovernments;
use App\Filament\Resources\LocalGovernments\RelationManagers\TerritoriesRelationManager;
use App\Filament\Resources\LocalGovernments\Schemas\LocalGovernmentForm;
use App\Filament\Resources\LocalGovernments\Tables\LocalGovernmentsTable;
use App\Models\LocalGovernment;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LocalGovernmentResource extends Resource
{
    protected static ?string $model = LocalGovernment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Local Governments';

    protected static ?string $modelLabel = 'Local government';

    protected static ?string $pluralModelLabel = 'Local governments';

    protected static string|UnitEnum|null $navigationGroup = 'Geography';

    public static function form(Schema $schema): Schema
    {
        return LocalGovernmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocalGovernmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TerritoriesRelationManager::class,
        ];
    }

    /**
     * @return Builder<LocalGovernment>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<LocalGovernment> $query */
        $query = parent::getEloquentQuery()
            ->with('state.country')
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
            'index' => ListLocalGovernments::route('/'),
            'create' => CreateLocalGovernment::route('/create'),
            'edit' => EditLocalGovernment::route('/{record}/edit'),
        ];
    }
}
