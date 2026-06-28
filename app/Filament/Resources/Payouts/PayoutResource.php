<?php

namespace App\Filament\Resources\Payouts;

use App\Enums\PlatformPermission;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Resources\Payouts\Pages\ViewPayout;
use App\Filament\Resources\Payouts\Schemas\PayoutInfolist;
use App\Filament\Resources\Payouts\Tables\PayoutsTable;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Payouts';

    public static function infolist(Schema $schema): Schema
    {
        return PayoutInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<Payout>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<Payout> $query */
        $query = parent::getEloquentQuery()
            ->with(['artisanProfile.localGovernment', 'batch', 'payoutAccount', 'wallet', 'requestedBy', 'approvedBy', 'processedBy'])
            ->withCount('attempts');

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

        return $user instanceof User
            && ($user->can(PlatformPermission::ViewPayments->value) || $user->can(PlatformPermission::ManagePayouts->value));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
            'view' => ViewPayout::route('/{record}'),
        ];
    }
}
