<?php

namespace App\Actions\Operations;

use App\Enums\ArtisanVerificationStatus;
use App\Enums\KycRiskLevel;
use App\Models\KycSubmission;
use App\Models\ReasonCode;
use App\Models\User;

class ApproveKyc
{
    public function __construct(
        private readonly ReviewKyc $reviewKyc,
    ) {}

    public function handle(
        KycSubmission $submission,
        User $reviewer,
        ReasonCode $reasonCode,
        ?string $notes = null,
        ?KycRiskLevel $riskLevel = null,
    ): KycSubmission {
        return $this->reviewKyc->transition(
            submission: $submission,
            reviewer: $reviewer,
            targetStatus: ArtisanVerificationStatus::Approved,
            reasonCode: $reasonCode,
            notes: $notes,
            riskLevel: $riskLevel,
        );
    }
}
