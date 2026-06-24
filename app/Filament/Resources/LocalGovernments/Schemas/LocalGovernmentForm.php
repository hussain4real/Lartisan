<?php

namespace App\Filament\Resources\LocalGovernments\Schemas;

use App\Filament\Resources\States\StateResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class LocalGovernmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('state_id')
                    ->relationship(
                        name: 'state',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereIn('id', StateResource::getEloquentQuery()->select('id'))
                            ->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
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
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('state_id', self::scalarFormValue($get, 'state_id'))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('state_id', self::scalarFormValue($get, 'state_id'))),
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
