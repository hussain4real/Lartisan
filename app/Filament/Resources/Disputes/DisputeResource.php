<?php

namespace App\Filament\Resources\Disputes;

use App\Filament\Resources\Disputes\Pages\ListDisputes;
use App\Filament\Resources\Disputes\Pages\ViewDispute;
use App\Filament\Resources\Disputes\Schemas\DisputeInfolist;
use App\Filament\Resources\Disputes\Tables\DisputesTable;
use App\Models\ArtisanProfile;
use App\Models\Dispute;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Disputes';

    public static function infolist(Schema $schema): Schema
    {
        return DisputeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisputesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<Dispute>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Dispute> $query */
        $query = parent::getEloquentQuery()
            ->with(['artisanProfile.localGovernment', 'booking', 'customer', 'openedBy', 'resolvedBy', 'review']);

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'artisan_profile_id',
            ArtisanProfile::query()->visibleTo($user)->select('id'),
        );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('support.cases.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisputes::route('/'),
            'view' => ViewDispute::route('/{record}'),
        ];
    }
}
