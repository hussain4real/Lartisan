<?php

namespace App\Filament\Resources\ArtisanProfiles;

use App\Filament\Resources\ArtisanProfiles\Pages\ListArtisanProfiles;
use App\Filament\Resources\ArtisanProfiles\Pages\ViewArtisanProfile;
use App\Filament\Resources\ArtisanProfiles\Schemas\ArtisanProfileInfolist;
use App\Filament\Resources\ArtisanProfiles\Tables\ArtisanProfilesTable;
use App\Models\ArtisanProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArtisanProfileResource extends Resource
{
    protected static ?string $model = ArtisanProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'business_name';

    protected static ?string $navigationLabel = 'Artisan Profiles';

    public static function infolist(Schema $schema): Schema
    {
        return ArtisanProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArtisanProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<ArtisanProfile>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<ArtisanProfile> $query */
        $query = parent::getEloquentQuery()
            ->with(['user', 'localGovernment', 'territory', 'suspensionReasonCode']);

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->visibleTo($user);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArtisanProfiles::route('/'),
            'view' => ViewArtisanProfile::route('/{record}'),
        ];
    }
}
