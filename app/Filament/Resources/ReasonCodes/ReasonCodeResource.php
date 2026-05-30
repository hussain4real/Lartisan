<?php

namespace App\Filament\Resources\ReasonCodes;

use App\Enums\PlatformPermission;
use App\Filament\Resources\ReasonCodes\Pages\CreateReasonCode;
use App\Filament\Resources\ReasonCodes\Pages\EditReasonCode;
use App\Filament\Resources\ReasonCodes\Pages\ListReasonCodes;
use App\Filament\Resources\ReasonCodes\Schemas\ReasonCodeForm;
use App\Filament\Resources\ReasonCodes\Tables\ReasonCodesTable;
use App\Models\ReasonCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReasonCodeResource extends Resource
{
    protected static ?string $model = ReasonCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|UnitEnum|null $navigationGroup = 'Operations Settings';

    public static function form(Schema $schema): Schema
    {
        return ReasonCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReasonCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(PlatformPermission::ManagePlatformSettings->value) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReasonCodes::route('/'),
            'create' => CreateReasonCode::route('/create'),
            'edit' => EditReasonCode::route('/{record}/edit'),
        ];
    }
}
