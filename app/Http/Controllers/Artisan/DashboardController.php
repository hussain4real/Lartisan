<?php

namespace App\Http\Controllers\Artisan;

use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\KycSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function __invoke(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('view', $profile);

        $profile->loadCount(['services', 'kycSubmissions', 'fieldVisits']);

        return Inertia::render('artisan/Dashboard', [
            'profile' => $this->profileSummary($profile),
            'metrics' => [
                'services' => $this->integerAttribute($profile, 'services_count'),
                'kycSubmissions' => $this->integerAttribute($profile, 'kyc_submissions_count'),
                'fieldVisits' => $this->integerAttribute($profile, 'field_visits_count'),
            ],
            'latestKyc' => $this->kycSummary($profile->kycSubmissions()->latest('id')->first()),
            'recentServices' => $profile->services()
                ->with('category')
                ->orderBy('sort_order')
                ->limit(5)
                ->get()
                ->map(fn (ArtisanService $service): array => $this->serviceSummary($service))
                ->all(),
        ]);
    }

    /**
     * @return array{id: int, businessName: string, verificationStatus: string, availabilityStatus: string, isPublic: bool}
     */
    private function profileSummary(ArtisanProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'businessName' => $profile->business_name,
            'verificationStatus' => $profile->verification_status->value,
            'availabilityStatus' => $profile->availability_status->value,
            'isPublic' => $profile->is_public,
        ];
    }

    /**
     * @return array{id: int, status: string, submittedAt: string|null}|null
     */
    private function kycSummary(?KycSubmission $submission): ?array
    {
        if (! $submission instanceof KycSubmission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'status' => $submission->status->value,
            'submittedAt' => $submission->submitted_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, title: string, category: string, status: string}
     */
    private function serviceSummary(ArtisanService $service): array
    {
        $category = $service->category()->firstOrFail();

        return [
            'id' => $service->id,
            'title' => $service->title,
            'category' => $category->name,
            'status' => $service->status->value,
        ];
    }

    private function integerAttribute(ArtisanProfile $profile, string $attribute): int
    {
        $value = $profile->getAttribute($attribute);

        return is_numeric($value) ? (int) $value : 0;
    }
}
