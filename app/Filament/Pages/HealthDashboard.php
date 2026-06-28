<?php

namespace App\Filament\Pages;

use App\Actions\Operations\CollectSystemHealthSnapshot;
use App\Enums\PlatformRole;
use App\Models\RestoreTestRecord;
use App\Models\SystemHealthSnapshot;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class HealthDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Health & recovery';

    protected static ?string $title = 'Health & Recovery';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.health-dashboard';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $currentPanel = Filament::getCurrentPanel();

        return $user instanceof User
            && $user->hasRole(PlatformRole::SuperAdmin->value)
            && ($currentPanel === null || $currentPanel->getId() === 'admin');
    }

    public function latestSnapshot(): ?SystemHealthSnapshot
    {
        return SystemHealthSnapshot::query()
            ->with(['generatedBy'])
            ->latest('generated_at')
            ->first();
    }

    public function latestRestoreTest(): ?RestoreTestRecord
    {
        return RestoreTestRecord::query()
            ->with(['verifiedBy'])
            ->latest('tested_at')
            ->first();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function checks(): array
    {
        $checks = $this->latestSnapshot()?->checks;

        return is_array($checks) ? $checks : [];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('collectHealth')
                ->label('Collect snapshot')
                ->visible(fn (): bool => self::canAccess())
                ->action(function (): void {
                    /** @var User|null $actor */
                    $actor = auth()->user();
                    $snapshot = app(CollectSystemHealthSnapshot::class)->handle($actor);

                    Notification::make()
                        ->title('Health snapshot collected')
                        ->body('Status: '.$snapshot->status->value)
                        ->status($snapshot->status->value === 'passing' ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }
}
