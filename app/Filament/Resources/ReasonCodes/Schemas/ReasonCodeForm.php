<?php

namespace App\Filament\Resources\ReasonCodes\Schemas;

use App\Enums\ReasonCodeCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReasonCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category')
                    ->options(ReasonCodeCategory::class)
                    ->required(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
