<?php

namespace App\Filament\Resources\Territories\Schemas;

use App\Enums\TerritoryType;
use App\Filament\Resources\LocalGovernments\LocalGovernmentResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class TerritoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('local_government_id')
                    ->relationship(
                        name: 'localGovernment',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereIn('id', LocalGovernmentResource::getEloquentQuery()->select('id'))
                            ->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->options(TerritoryType::class)
                    ->required(),
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        if (($get('slug') ?? '') !== Str::slug($old ?? '')) {
                            return;
                        }

                        $set('slug', Str::slug($state ?? ''));
                    })
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('local_government_id', self::scalarFormValue($get, 'local_government_id'))
                        ->where('type', self::scalarFormValue($get, 'type'))),
                Textarea::make('boundaries')
                    ->json()
                    ->formatStateUsing(function (mixed $state): ?string {
                        if ($state === null || is_string($state)) {
                            return $state;
                        }

                        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                        return is_string($encoded) ? $encoded : null;
                    })
                    ->dehydrateStateUsing(function (?string $state): ?array {
                        $trimmed = trim($state ?? '');

                        if ($trimmed === '') {
                            return null;
                        }

                        $decoded = json_decode($trimmed, true);

                        return is_array($decoded) ? $decoded : null;
                    })
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }

    private static function scalarFormValue(Get $get, string $key): int|string|null
    {
        $value = $get($key);

        if (is_int($value) || is_string($value)) {
            return $value;
        }

        return null;
    }
}
