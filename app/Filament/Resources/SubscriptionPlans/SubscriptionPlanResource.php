<?php

namespace App\Filament\Resources\SubscriptionPlans;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PlatformPermission;
use App\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Filament\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Operations Settings';

    public static function form(Schema $schema): Schema
    {
        return SubscriptionPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Builder<SubscriptionPlan>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        /** @var Builder<SubscriptionPlan> $query */
        $query = parent::getEloquentQuery()
            ->withCount(['subscriptions', 'payments'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if (! $user instanceof User || ! $user->can(PlatformPermission::ManagePlatformSettings->value)) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can(PlatformPermission::ManagePlatformSettings->value);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }

    /**
     * Build a structured audit payload snapshot for a subscription plan.
     *
     * @return array{name: string, slug: string, description: string|null, price_amount: int, currency_code: string, interval: string, duration_days: int, sort_order: int, active: bool, includes_team_management: bool, feature_summary: array<int, string>}
     */
    public static function auditPayload(SubscriptionPlan $plan): array
    {
        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price_amount' => $plan->price_amount,
            'currency_code' => $plan->currency_code,
            'interval' => $plan->interval->value,
            'duration_days' => $plan->duration_days,
            'sort_order' => $plan->sort_order,
            'active' => $plan->active,
            'includes_team_management' => $plan->includes_team_management,
            'feature_summary' => array_values(array_filter(
                $plan->feature_summary ?? [],
                static fn (string $feature): bool => $feature !== '',
            )),
        ];
    }

    /**
     * Persist an append-only audit log entry for a subscription plan mutation.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function recordAudit(string $action, SubscriptionPlan $plan, ?array $before, ?array $after): void
    {
        $actor = auth()->user();

        app(RecordAuditLog::class)->handle(
            actor: $actor instanceof User ? $actor : null,
            action: $action,
            subject: $plan,
            before: $before,
            after: $after,
        );
    }
}
