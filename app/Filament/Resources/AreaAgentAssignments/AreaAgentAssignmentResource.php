<?php

namespace App\Filament\Resources\AreaAgentAssignments;

use App\Filament\Resources\AreaAgentAssignments\Pages\ListAreaAgentAssignments;
use App\Filament\Resources\AreaAgentAssignments\Pages\ViewAreaAgentAssignment;
use App\Filament\Resources\AreaAgentAssignments\Schemas\AreaAgentAssignmentInfolist;
use App\Filament\Resources\AreaAgentAssignments\Tables\AreaAgentAssignmentsTable;
use App\Models\AreaAgentAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AreaAgentAssignmentResource extends Resource
{
    protected static ?string $model = AreaAgentAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Agent Territories';

    public static function infolist(Schema $schema): Schema
    {
        return AreaAgentAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AreaAgentAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<AreaAgentAssignment>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<AreaAgentAssignment> $query */
        $query = parent::getEloquentQuery()
            ->with(['user', 'territory.localGovernment', 'assignedBy', 'reasonCode']);

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
            'index' => ListAreaAgentAssignments::route('/'),
            'view' => ViewAreaAgentAssignment::route('/{record}'),
        ];
    }
}
