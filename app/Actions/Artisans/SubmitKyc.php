<?php

namespace App\Actions\Artisans;

use App\Enums\ArtisanVerificationStatus;
use App\Models\ArtisanProfile;
use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitKyc
{
    public function __construct(
        private readonly RecordStatusHistory $recordStatusHistory,
    ) {}

    public function handle(
        ArtisanProfile $profile,
        User $actor,
        ?string $notes = null,
    ): KycSubmission {
        return DB::transaction(function () use ($profile, $actor, $notes): KycSubmission {
            $submission = $this->submissionReadyForReview($profile);
            $previousSubmissionStatus = $submission->status;
            $previousProfileStatus = $profile->verification_status;

            $submission->update([
                'status' => ArtisanVerificationStatus::Submitted,
                'submitted_at' => now(),
                'notes' => $this->blankToNull($notes),
            ]);

            $profile->update([
                'verification_status' => ArtisanVerificationStatus::Submitted,
            ]);

            $this->recordStatusHistory->handle(
                statusable: $submission,
                actor: $actor,
                fromStatus: $previousSubmissionStatus->value,
                toStatus: ArtisanVerificationStatus::Submitted->value,
                reason: 'kyc.submitted',
            );

            if ($previousProfileStatus !== ArtisanVerificationStatus::Submitted) {
                $this->recordStatusHistory->handle(
                    statusable: $profile,
                    actor: $actor,
                    fromStatus: $previousProfileStatus->value,
                    toStatus: ArtisanVerificationStatus::Submitted->value,
                    reason: 'artisan.profile.kyc_submitted',
                );
            }

            return $submission->refresh();
        });
    }

    private function submissionReadyForReview(ArtisanProfile $profile): KycSubmission
    {
        $reviewableStatuses = [
            ArtisanVerificationStatus::Draft->value,
            ArtisanVerificationStatus::Returned->value,
            ArtisanVerificationStatus::Rejected->value,
        ];

        $submission = $profile->kycSubmissions()
            ->whereIn('status', $reviewableStatuses)
            ->latest('id')
            ->first();

        if ($submission instanceof KycSubmission) {
            return $submission;
        }

        $openSubmissionExists = $profile->kycSubmissions()
            ->whereNotIn('status', $reviewableStatuses)
            ->exists();

        if ($openSubmissionExists) {
            throw new InvalidArgumentException('This artisan already has a KYC submission in review.');
        }

        return $profile->kycSubmissions()->create([
            'status' => ArtisanVerificationStatus::Draft,
        ]);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
