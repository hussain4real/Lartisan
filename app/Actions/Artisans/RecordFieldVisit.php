<?php

namespace App\Actions\Artisans;

use App\Enums\ArtisanVerificationStatus;
use App\Enums\FieldVisitStatus;
use App\Models\ArtisanProfile;
use App\Models\FieldVisit;
use App\Models\KycSubmission;
use App\Models\Territory;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordFieldVisit
{
    public function __construct(
        private readonly RecordStatusHistory $recordStatusHistory,
    ) {}

    /**
     * @param  array<string, mixed>|null  $checklist
     */
    public function handle(
        ArtisanProfile $profile,
        User $areaAgent,
        ?KycSubmission $submission = null,
        ?Territory $territory = null,
        FieldVisitStatus $status = FieldVisitStatus::Scheduled,
        ?DateTimeInterface $visitedAt = null,
        ?string $latitude = null,
        ?string $longitude = null,
        ?string $notes = null,
        ?array $checklist = null,
    ): FieldVisit {
        $this->ensureVisitScope($profile, $submission, $territory);

        return DB::transaction(function () use ($profile, $areaAgent, $submission, $territory, $status, $visitedAt, $latitude, $longitude, $notes, $checklist): FieldVisit {
            $visit = $profile->fieldVisits()->create([
                'kyc_submission_id' => $submission?->id,
                'area_agent_id' => $areaAgent->id,
                'territory_id' => $territory?->id,
                'status' => $status,
                'visited_at' => $visitedAt,
                'latitude' => $this->blankToNull($latitude),
                'longitude' => $this->blankToNull($longitude),
                'notes' => $this->blankToNull($notes),
                'checklist' => $checklist,
            ]);

            $this->recordStatusHistory->handle(
                statusable: $visit,
                actor: $areaAgent,
                fromStatus: null,
                toStatus: $status->value,
                reason: 'field_visit.recorded',
            );

            $this->transitionVerificationStatus($profile, $areaAgent, $submission, $status);

            return $visit->refresh();
        });
    }

    private function ensureVisitScope(
        ArtisanProfile $profile,
        ?KycSubmission $submission,
        ?Territory $territory,
    ): void {
        if ($submission instanceof KycSubmission && $submission->artisan_profile_id !== $profile->id) {
            throw new InvalidArgumentException('The selected KYC submission does not belong to this artisan profile.');
        }

        if ($territory instanceof Territory
            && $profile->local_government_id !== null
            && $territory->local_government_id !== $profile->local_government_id) {
            throw new InvalidArgumentException('The selected territory does not belong to the artisan profile local government.');
        }
    }

    private function transitionVerificationStatus(
        ArtisanProfile $profile,
        User $areaAgent,
        ?KycSubmission $submission,
        FieldVisitStatus $status,
    ): void {
        $targetStatus = match ($status) {
            FieldVisitStatus::Completed => ArtisanVerificationStatus::FieldCheckComplete,
            FieldVisitStatus::Scheduled, FieldVisitStatus::InProgress => ArtisanVerificationStatus::FieldCheckPending,
            default => null,
        };

        if (! $targetStatus instanceof ArtisanVerificationStatus) {
            return;
        }

        $previousProfileStatus = $profile->verification_status;
        $profile->update(['verification_status' => $targetStatus]);

        $this->recordStatusHistory->handle(
            statusable: $profile,
            actor: $areaAgent,
            fromStatus: $previousProfileStatus->value,
            toStatus: $targetStatus->value,
            reason: 'artisan.profile.field_visit_updated',
        );

        if (! $submission instanceof KycSubmission) {
            return;
        }

        $previousSubmissionStatus = $submission->status;
        $submission->update(['status' => $targetStatus]);

        $this->recordStatusHistory->handle(
            statusable: $submission,
            actor: $areaAgent,
            fromStatus: $previousSubmissionStatus->value,
            toStatus: $targetStatus->value,
            reason: 'kyc.field_visit_updated',
        );
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
