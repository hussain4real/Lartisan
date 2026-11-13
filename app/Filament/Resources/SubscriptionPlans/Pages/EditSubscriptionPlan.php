<?php

namespace App\Filament\Resources\SubscriptionPlans\Pages;

use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    /**
     * @var array{name: string, slug: string, description: string|null, price_amount: int, currency_code: string, interval: string, duration_days: int, sort_order: int, active: bool, includes_team_management: bool, feature_summary: array<int, string>}|null
     */
    protected ?array $beforeSnapshot = null;

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof SubscriptionPlan) {
            $fresh = $record->fresh();
            $this->beforeSnapshot = SubscriptionPlanResource::auditPayload($fresh instanceof SubscriptionPlan ? $fresh : $record);
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof SubscriptionPlan) {
            SubscriptionPlanResource::recordAudit(
                'subscription_plan.updated',
                $record,
                $this->beforeSnapshot,
                SubscriptionPlanResource::auditPayload($record),
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (SubscriptionPlan $record): void {
                    SubscriptionPlanResource::recordAudit(
                        'subscription_plan.deleted',
                        $record,
                        SubscriptionPlanResource::auditPayload($record),
                        null,
                    );
                }),
        ];
    }
}
