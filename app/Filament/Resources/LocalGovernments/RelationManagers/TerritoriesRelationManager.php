<?php

namespace App\Filament\Resources\LocalGovernments\RelationManagers;

use App\Enums\TerritoryType;
use App\Models\LocalGovernment;
use App\Models\Territory;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class TerritoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'territories';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $ownerRecord instanceof LocalGovernment
            && Gate::forUser($user)->allows('createForLocalGovernment', [Territory::class, $ownerRecord]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get, RelationManager $livewire): Unique => $rule
                        ->where('local_government_id', self::ownerKey($livewire))
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(TerritoryType::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    private static function ownerKey(RelationManager $livewire): int|string|null
    {
        $ownerRecord = $livewire->getOwnerRecord();

        if (! $ownerRecord instanceof LocalGovernment) {
            return null;
        }

        $key = $ownerRecord->getKey();

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        return null;
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
