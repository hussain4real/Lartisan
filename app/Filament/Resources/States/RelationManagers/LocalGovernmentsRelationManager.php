<?php

namespace App\Filament\Resources\States\RelationManagers;

use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class LocalGovernmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'localGovernments';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $ownerRecord instanceof State
            && Gate::forUser($user)->allows('createForState', [LocalGovernment::class, $ownerRecord]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, RelationManager $livewire): Unique => $rule->where('state_id', self::ownerKey($livewire))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, RelationManager $livewire): Unique => $rule->where('state_id', self::ownerKey($livewire))),
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
                //
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

        if (! $ownerRecord instanceof State) {
            return null;
        }

        $key = $ownerRecord->getKey();

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        return null;
    }
}
