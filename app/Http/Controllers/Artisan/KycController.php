<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Artisans\AttachKycMedia;
use App\Actions\Artisans\SubmitKyc;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\SubmitKycRequest;
use App\Models\KycSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class KycController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function show(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('view', $profile);

        return Inertia::render('artisan/Kyc', [
            'profile' => [
                'id' => $profile->id,
                'businessName' => $profile->business_name,
                'verificationStatus' => $profile->verification_status->value,
            ],
            'collections' => KycSubmission::mediaCollectionNames(),
            'latestSubmission' => $this->submissionPayload(
                $profile->kycSubmissions()->with('media')->latest('id')->first(),
            ),
        ]);
    }

    public function store(
        SubmitKycRequest $request,
        SubmitKyc $submitKyc,
        AttachKycMedia $attachKycMedia,
    ): RedirectResponse {
        $profile = $this->artisanProfileFrom($request);
        $user = $this->userFrom($request);

        Gate::authorize('create', [KycSubmission::class, $profile]);

        $submission = $submitKyc->handle(
            profile: $profile,
            actor: $user,
            notes: $request->notes(),
        );

        foreach ($request->mediaFiles() as $collectionName => $file) {
            $attachKycMedia->handle($submission, $file, $collectionName, $user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('KYC submitted.')]);

        return to_route('artisan.kyc.show', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }

    /**
     * @return array{id: int, status: string, submittedAt: string|null, notes: string|null, media: array<string, array{id: int, name: string, fileName: string}|null>}|null
     */
    private function submissionPayload(?KycSubmission $submission): ?array
    {
        if (! $submission instanceof KycSubmission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'status' => $submission->status->value,
            'submittedAt' => $submission->submitted_at?->toISOString(),
            'notes' => $submission->notes,
            'media' => collect(KycSubmission::mediaCollectionNames())
                ->mapWithKeys(fn (string $collectionName): array => [
                    $collectionName => $this->mediaPayload($submission->getFirstMedia($collectionName)),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, fileName: string}|null
     */
    private function mediaPayload(?Media $media): ?array
    {
        if (! $media instanceof Media) {
            return null;
        }

        return [
            'id' => $media->id,
            'name' => $media->name,
            'fileName' => $media->file_name,
        ];
    }
}
