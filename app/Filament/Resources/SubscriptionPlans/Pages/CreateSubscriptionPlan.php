<?php

namespace App\Filament\Resources\SubscriptionPlans\Pages;

use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof SubscriptionPlan) {
            SubscriptionPlanResource::recordAudit(
                'subscription_plan.created',
                $record,
                null,
                SubscriptionPlanResource::auditPayload($record),
            );
        }
    }
}
