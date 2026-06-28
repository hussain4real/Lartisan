<?php

namespace App\Actions\Payouts;

use App\Actions\Notifications\SendLifecycleNotification;
use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
use App\Models\Payout;
use App\Models\SupportCase;

class CreatePayoutExceptionCase
{
    public function __construct(
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Payout $payout,
        string $subject,
        ?string $description = null,
        SupportCasePriority $priority = SupportCasePriority::Normal,
        array $metadata = [],
    ): SupportCase {
        $supportCase = SupportCase::query()
            ->where('supportable_type', $payout->getMorphClass())
            ->where('supportable_id', $payout->id)
            ->where('category', SupportCaseCategory::Payout)
            ->where('subject', $subject)
            ->whereIn('status', [SupportCaseStatus::Open->value, SupportCaseStatus::InProgress->value])
            ->first();

        if ($supportCase instanceof SupportCase) {
            return $supportCase;
        }

        $supportCase = SupportCase::query()->create([
            'requester_id' => $payout->requested_by ?? $payout->artisanProfile()->value('user_id'),
            'supportable_type' => $payout->getMorphClass(),
            'supportable_id' => $payout->id,
            'category' => SupportCaseCategory::Payout,
            'priority' => $priority,
            'status' => SupportCaseStatus::Open,
            'subject' => $subject,
            'description' => $description,
            'opened_at' => now(),
            'metadata' => [
                'payout_id' => $payout->id,
                'provider_reference' => $payout->provider_reference,
                'provider_transfer_code' => $payout->provider_transfer_code,
                ...$metadata,
            ],
        ]);

        $this->sendLifecycleNotification->supportCaseOpened($supportCase);

        return $supportCase->refresh();
    }
}
