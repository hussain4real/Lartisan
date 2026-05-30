<?php

namespace App\Filament\Resources\KycSubmissions;

use App\Filament\Resources\KycSubmissions\Pages\ListKycSubmissions;
use App\Filament\Resources\KycSubmissions\Pages\ViewKycSubmission;
use App\Filament\Resources\KycSubmissions\Schemas\KycSubmissionInfolist;
use App\Filament\Resources\KycSubmissions\Tables\KycSubmissionsTable;
use App\Models\ArtisanProfile;
use App\Models\KycSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KycSubmissionResource extends Resource
{
    protected static ?string $model = KycSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'KYC Queue';

    public static function infolist(Schema $schema): Schema
    {
        return KycSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KycSubmissionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<KycSubmission>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<KycSubmission> $query */
        $query = parent::getEloquentQuery()
            ->with(['artisanProfile.localGovernment', 'artisanProfile.territory', 'reasonCode', 'reviewedBy']);

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'artisan_profile_id',
            ArtisanProfile::query()->visibleTo($user)->select('id'),
        );
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKycSubmissions::route('/'),
            'view' => ViewKycSubmission::route('/{record}'),
        ];
    }
}
