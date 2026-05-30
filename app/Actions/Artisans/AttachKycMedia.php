<?php

namespace App\Actions\Artisans;

use App\Enums\ArtisanVerificationStatus;
use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AttachKycMedia
{
    public function handle(
        KycSubmission $submission,
        UploadedFile $file,
        string $collectionName,
        User $actor,
    ): Media {
        if (! in_array($collectionName, KycSubmission::mediaCollectionNames(), true)) {
            throw new InvalidArgumentException('The selected KYC media collection is not supported.');
        }

        if ($submission->status === ArtisanVerificationStatus::Approved) {
            throw new InvalidArgumentException('Approved KYC submissions cannot accept new media.');
        }

        return $submission
            ->addMedia($file)
            ->withCustomProperties(['uploaded_by' => $actor->id])
            ->toMediaCollection($collectionName);
    }
}
