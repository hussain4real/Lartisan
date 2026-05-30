<?php

namespace App\Filament\Resources\AreaAgentAssignments\Pages;

use App\Actions\Operations\AssignTerritory;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\ReasonCodeCategory;
use App\Filament\Resources\AreaAgentAssignments\AreaAgentAssignmentResource;
use App\Models\ReasonCode;
use App\Models\Territory;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;

class ListAreaAgentAssignments extends ListRecords
{
    protected static string $resource = AreaAgentAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignTerritory')
                ->label('Assign territory')
                ->visible(fn (): bool => auth()->user()?->can(PlatformPermission::AssignTerritories->value) ?? false)
                ->schema([
                    Select::make('area_agent_id')
                        ->label('Area agent')
                        ->options(fn (): array => User::role(PlatformRole::AreaAgent->value)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('territory_id')
                        ->label('Territory')
                        ->options(fn (): array => Territory::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('reason_code_id')
                        ->label('Reason')
                        ->options(fn (): array => ReasonCode::query()
                            ->forCategory(ReasonCodeCategory::TerritoryAssignment)
                            ->active()
                            ->orderBy('label')
                            ->pluck('label', 'id')
                            ->all())
                        ->required(),
                    Textarea::make('reason')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    /** @var User $actor */
                    $actor = auth()->user();
                    $reason = isset($data['reason']) && is_string($data['reason'])
                        ? $data['reason']
                        : null;

                    app(AssignTerritory::class)->handle(
                        areaAgent: User::query()->findOrFail(self::integerFormValue($data, 'area_agent_id')),
                        territory: Territory::query()->findOrFail(self::integerFormValue($data, 'territory_id')),
                        actor: $actor,
                        reasonCode: ReasonCode::query()->findOrFail(self::integerFormValue($data, 'reason_code_id')),
                        reason: $reason,
                    );
                }),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function integerFormValue(array $data, string $key): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);

        throw_if($value === false, InvalidArgumentException::class, "Invalid {$key} value.");

        return $value;
    }
}
