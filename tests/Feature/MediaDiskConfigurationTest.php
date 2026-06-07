<?php

use App\Models\ArtisanProfile;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\KycSubmission;
use App\Support\MediaDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    config()->set('filesystems.media.portfolio_disk', 'media_public');
    config()->set('filesystems.media.private_disk', 'media_private');

    Storage::fake('media_public');
    Storage::fake('media_private');
});

afterEach(function () {
    config()->set('filesystems.media.portfolio_disk', 'public');
    config()->set('filesystems.media.private_disk', 'local');
});

test('artisan portfolio media uses the configured public cloud disk', function () {
    $profile = ArtisanProfile::factory()->create();

    $profile
        ->addMedia(UploadedFile::fake()->image('portfolio.jpg'))
        ->toMediaCollection(ArtisanProfile::PORTFOLIO_COLLECTION);

    $media = $profile->getFirstMedia(ArtisanProfile::PORTFOLIO_COLLECTION);

    if (! $media instanceof Media) {
        throw new RuntimeException('Portfolio media was not stored.');
    }

    expect($media->disk)->toBe('media_public');

    Storage::disk('media_public')->assertExists($media->getPathRelativeToRoot());
});

test('private kyc booking and dispute media use the configured private cloud disk', function () {
    $profile = ArtisanProfile::factory()->create();
    $submission = KycSubmission::factory()->create(['artisan_profile_id' => $profile->id]);
    $booking = Booking::factory()->create(['artisan_profile_id' => $profile->id]);
    $dispute = Dispute::factory()->create([
        'artisan_profile_id' => $profile->id,
        'booking_id' => $booking->id,
    ]);

    $submission
        ->addMedia(UploadedFile::fake()->image('government-id.jpg'))
        ->toMediaCollection(KycSubmission::GOVERNMENT_ID_COLLECTION);

    $booking
        ->addMedia(UploadedFile::fake()->image('booking-evidence.jpg'))
        ->toMediaCollection(Booking::MEDIA_COLLECTION);

    $dispute
        ->addMedia(UploadedFile::fake()->image('dispute-evidence.jpg'))
        ->toMediaCollection(Dispute::EVIDENCE_COLLECTION);

    $mediaItems = [
        $submission->getFirstMedia(KycSubmission::GOVERNMENT_ID_COLLECTION),
        $booking->getFirstMedia(Booking::MEDIA_COLLECTION),
        $dispute->getFirstMedia(Dispute::EVIDENCE_COLLECTION),
    ];

    foreach ($mediaItems as $media) {
        if (! $media instanceof Media) {
            throw new RuntimeException('Private media was not stored.');
        }

        expect($media->disk)->toBe('media_private');

        Storage::disk('media_private')->assertExists($media->getPathRelativeToRoot());
    }
});

test('media disk config falls back to local development disks when unset', function () {
    config()->set('filesystems.media.portfolio_disk', '');
    config()->set('filesystems.media.private_disk', null);

    expect(MediaDisk::portfolio())->toBe('public')
        ->and(MediaDisk::private())->toBe('local');
});
