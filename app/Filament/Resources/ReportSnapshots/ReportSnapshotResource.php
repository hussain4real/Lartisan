<?php

namespace App\Filament\Resources\ReportSnapshots;

use App\Enums\PlatformPermission;
use App\Filament\Resources\ReportSnapshots\Pages\ListReportSnapshots;
use App\Filament\Resources\ReportSnapshots\Pages\ViewReportSnapshot;
use App\Filament\Resources\ReportSnapshots\Schemas\ReportSnapshotInfolist;
use App\Filament\Resources\ReportSnapshots\Tables\ReportSnapshotsTable;
use App\Models\ReportSnapshot;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportSnapshotResource extends Resource
{
    protected static ?string $model = ReportSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Reports';

    public static function infolist(Schema $schema): Schema
    {
        return ReportSnapshotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportSnapshotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<ReportSnapshot>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<ReportSnapshot> $query */
        $query = parent::getEloquentQuery()->with(['generatedBy']);

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can(PlatformPermission::ViewGlobalReports->value)) {
            return $query;
        }

        return $query->where('generated_by', $user->id);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && collect([
                PlatformPermission::ViewGlobalReports,
                PlatformPermission::ViewStateReports,
                PlatformPermission::ViewLocalGovernmentReports,
                PlatformPermission::ViewAreaReports,
            ])->contains(fn (PlatformPermission $permission): bool => $user->can($permission->value));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportSnapshots::route('/'),
            'view' => ViewReportSnapshot::route('/{record}'),
        ];
    }
}
