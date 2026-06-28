<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class PrivateMediaUrl
{
    public function for(Media $media, ?DateTimeInterface $expiresAt = null): string
    {
        try {
            return Storage::disk($media->disk)->temporaryUrl(
                $media->getPathRelativeToRoot(),
                $expiresAt ?? now()->addMinutes($this->expirationMinutes()),
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.$media->file_name.'"',
                    'ResponseContentType' => $media->mime_type,
                ],
            );
        } catch (Throwable $throwable) {
            app(ProviderFailureLogger::class)->report('storage', 'private-media-temporary-url', $throwable, [
                'disk' => $media->disk,
                'media_id' => $media->id,
                'model_type' => $media->model_type,
                'model_id' => $media->model_id,
            ]);

            throw $throwable;
        }
    }

    private function expirationMinutes(): int
    {
        $minutes = config('lartisan.hardening.private_media_url_expiration_minutes', 10);

        return max(1, is_numeric($minutes) ? (int) $minutes : 10);
    }
}
