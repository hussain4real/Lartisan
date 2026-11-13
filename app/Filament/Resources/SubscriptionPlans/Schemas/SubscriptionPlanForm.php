<?php

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use App\Enums\SubscriptionInterval;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('interval')
                    ->options(SubscriptionInterval::class)
                    ->default(SubscriptionInterval::Monthly)
                    ->required(),
                TextInput::make('duration_days')
                    ->label('Duration (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3660)
                    ->default(30)
                    ->required(),
                TextInput::make('price_amount')
                    ->label('Price amount (minor units / kobo)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('currency_code')
                    ->default('NGN')
                    ->maxLength(3)
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(10)
                    ->required(),
                Textarea::make('description')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                TagsInput::make('feature_summary')
                    ->label('Features')
                    ->placeholder('Add a plan feature')
                    ->columnSpanFull(),
                Toggle::make('includes_team_management')
                    ->label('Includes team management')
                    ->default(false)
                    ->required(),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
