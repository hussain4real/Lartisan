<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Actions\Reviews\ModerateReview;
use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rating')->sortable(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('moderation_signal')
                    ->label('Signal')
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('moderation_score')->label('Score')->sortable(),
                TextColumn::make('artisanProfile.business_name')
                    ->label('Artisan')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Guest')
                    ->searchable(),
                TextColumn::make('reviewed_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReviewStatus::class),
                SelectFilter::make('moderation_signal')->options([
                    'low_rating' => 'Low rating',
                    'keyword' => 'Keyword',
                    'low_rating_keyword' => 'Low rating + keyword',
                    'low_rating_no_comment' => 'Low rating without comment',
                    'review_velocity' => 'Review velocity',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::hideAction(),
            ])
            ->defaultSort('reviewed_at', 'desc');
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->schema([
                Textarea::make('notes')
                    ->required()
                    ->default('Review approved after moderation.')
                    ->maxLength(2000),
            ])
            ->visible(fn (Review $record): bool => self::canModerate($record)
                && $record->status !== ReviewStatus::Published)
            ->action(function (Review $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ModerateReview::class)->handle(
                    review: $record,
                    actor: $actor,
                    status: ReviewStatus::Published,
                    notes: is_string($data['notes'] ?? null) ? $data['notes'] : '',
                );
            });
    }

    private static function hideAction(): Action
    {
        return Action::make('hide')
            ->schema([
                Textarea::make('notes')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (Review $record): bool => self::canModerate($record)
                && $record->status !== ReviewStatus::Hidden)
            ->action(function (Review $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ModerateReview::class)->handle(
                    review: $record,
                    actor: $actor,
                    status: ReviewStatus::Hidden,
                    notes: is_string($data['notes'] ?? null) ? $data['notes'] : '',
                );
            });
    }

    private static function canModerate(Review $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && Gate::forUser($user)->allows('update', $record);
    }
}
