<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Filament\Resources\Reviews\Schemas\ReviewInfolist;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\ArtisanProfile;
use App\Models\Review;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Review moderation';

    public static function infolist(Schema $schema): Schema
    {
        return ReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<Review>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Review> $query */
        $query = parent::getEloquentQuery()
            ->with(['artisanProfile.localGovernment', 'booking', 'customer', 'moderatedBy', 'artisanResponseBy']);

        if (! $user instanceof User) {
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
            'index' => ListReviews::route('/'),
            'view' => ViewReview::route('/{record}'),
        ];
    }
}
