<?php

namespace App\Actions\Setup;

use App\Enums\ReasonCodeCategory;
use App\Models\ReasonCode;

class SeedReasonCodes
{
    public function handle(): void
    {
        foreach ($this->codes() as $code) {
            ReasonCode::query()->updateOrCreate(
                [
                    'category' => $code['category'],
                    'code' => $code['code'],
                ],
                [
                    'label' => $code['label'],
                    'description' => $code['description'],
                    'active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{category: ReasonCodeCategory, code: string, label: string, description: string}>
     */
    private function codes(): array
    {
        return [
            [
                'category' => ReasonCodeCategory::KycDecision,
                'code' => 'documents-complete',
                'label' => 'Documents Complete',
                'description' => 'Submitted documents satisfy the current verification requirement.',
            ],
            [
                'category' => ReasonCodeCategory::KycDecision,
                'code' => 'missing-document',
                'label' => 'Missing Document',
                'description' => 'Required verification evidence is missing or unreadable.',
            ],
            [
                'category' => ReasonCodeCategory::KycDecision,
                'code' => 'field-check-needed',
                'label' => 'Field Check Needed',
                'description' => 'Submission requires field verification before a final decision.',
            ],
            [
                'category' => ReasonCodeCategory::KycDecision,
                'code' => 'identity-mismatch',
                'label' => 'Identity Mismatch',
                'description' => 'Submitted identity evidence does not match the artisan record.',
            ],
            [
                'category' => ReasonCodeCategory::TerritoryAssignment,
                'code' => 'coverage-balancing',
                'label' => 'Coverage Balancing',
                'description' => 'Territory assignment changed to balance area-agent coverage.',
            ],
            [
                'category' => ReasonCodeCategory::TerritoryAssignment,
                'code' => 'agent-transfer',
                'label' => 'Agent Transfer',
                'description' => 'Area agent moved to a different operating territory.',
            ],
            [
                'category' => ReasonCodeCategory::Suspension,
                'code' => 'verification-concern',
                'label' => 'Verification Concern',
                'description' => 'Artisan profile suspended due to a verification or field report concern.',
            ],
            [
                'category' => ReasonCodeCategory::Suspension,
                'code' => 'policy-violation',
                'label' => 'Policy Violation',
                'description' => 'Artisan profile suspended after an operations policy violation.',
            ],
        ];
    }
}
