<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\SearchArtisans;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Marketplace\SearchArtisansRequest;
use App\Http\Requests\Marketplace\StoreBookingRequest;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MarketplaceController extends Controller
{
    public function index(SearchArtisansRequest $request, SearchArtisans $searchArtisans): Response
    {
        $results = $searchArtisans->handle(
            query: $request->queryText(),
            category: $request->category(),
            state: $request->state(),
            localGovernment: $request->localGovernment(),
            territory: $request->territory(),
        );

        return Inertia::render('marketplace/Index', [
            'filters' => [
                'query' => $request->queryText(),
                'serviceCategoryId' => $request->filled('service_category_id') ? $request->integer('service_category_id') : null,
                'stateId' => $request->filled('state_id') ? $request->integer('state_id') : null,
                'localGovernmentId' => $request->filled('local_government_id') ? $request->integer('local_government_id') : null,
                'territoryId' => $request->filled('territory_id') ? $request->integer('territory_id') : null,
            ],
            'categories' => $this->categoryOptions(),
            'states' => $this->stateOptions(),
            'artisans' => $results->map(fn (ArtisanProfile $profile): array => $this->artisanCardPayload($profile))->all(),
        ]);
    }

    public function show(ArtisanProfile $artisanProfile): Response
    {
        abort_unless($this->isVisibleMarketplaceProfile($artisanProfile), 404);

        return Inertia::render('marketplace/Show', [
            'artisan' => $this->artisanDetailPayload($artisanProfile->load(['services.category', 'state', 'localGovernment', 'territory'])),
        ]);
    }

    public function create(ArtisanProfile $artisanProfile): Response
    {
        abort_unless($this->isVisibleMarketplaceProfile($artisanProfile), 404);

        return Inertia::render('marketplace/Book', [
            'artisan' => $this->artisanDetailPayload($artisanProfile->load(['services.category', 'state', 'localGovernment', 'territory'])),
            'states' => $this->stateOptions(),
        ]);
    }

    public function store(
        StoreBookingRequest $request,
        ArtisanProfile $artisanProfile,
        CreateBooking $createBooking,
    ): RedirectResponse {
        $createdBooking = $createBooking->handle(
            profile: $artisanProfile,
            service: $request->service(),
            customer: $request->user(),
            customerName: $request->customerName(),
            customerPhone: $request->customerPhone(),
            customerEmail: $request->customerEmail(),
            addressSnapshot: $request->addressSnapshot(),
            scheduledAt: $request->scheduledAt(),
            description: $request->description(),
            attachments: $request->attachments(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking request sent.')]);

        return redirect()->to($createdBooking->trackerUrl());
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return ServiceCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, localGovernments: array<int, array{id: int, name: string, territories: array<int, array{id: int, name: string}>}>}>
     */
    private function stateOptions(): array
    {
        return State::query()
            ->where('active', true)
            ->with([
                'localGovernments' => function (Relation $query): void {
                    $query->getQuery()
                        ->where('active', true)
                        ->with('territories')
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (State $state): array => [
                'id' => $state->id,
                'name' => $state->name,
                'localGovernments' => $state->localGovernments
                    ->map(fn (LocalGovernment $localGovernment): array => [
                        'id' => $localGovernment->id,
                        'name' => $localGovernment->name,
                        'territories' => $localGovernment->territories
                            ->where('active', true)
                            ->sortBy('name')
                            ->values()
                            ->map(fn (Territory $territory): array => [
                                'id' => $territory->id,
                                'name' => $territory->name,
                            ])
                            ->all(),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array{id: int, businessName: string, publicSummary: string|null, availabilityStatus: string, verificationStatus: string, subscriptionStatus: string, yearsExperience: int|null, serviceRadiusKm: int|null, publicPhone: string|null, publicEmail: string|null, location: string, servicesCount: int, services: array<int, array{id: int, title: string, description: string|null, startingPrice: string|null, currencyCode: string, category: array{id: int, name: string}}>, portfolio: array<int, array{id: int, name: string, url: string}>}
     */
    private function artisanDetailPayload(ArtisanProfile $profile): array
    {
        return [
            ...$this->artisanCardPayload($profile),
            'publicSummary' => $profile->public_summary,
            'yearsExperience' => $profile->years_experience,
            'serviceRadiusKm' => $profile->service_radius_km,
            'publicPhone' => $profile->public_phone,
            'publicEmail' => $profile->public_email,
            'services' => $profile->services
                ->filter(fn (ArtisanService $service): bool => $service->status === ArtisanServiceStatus::Active)
                ->values()
                ->map(fn (ArtisanService $service): array => $this->servicePayload($service))
                ->all(),
            'portfolio' => $profile->getMedia(ArtisanProfile::PORTFOLIO_COLLECTION)
                ->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->getUrl(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, businessName: string, availabilityStatus: string, verificationStatus: string, subscriptionStatus: string, location: string, servicesCount: int}
     */
    private function artisanCardPayload(ArtisanProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'businessName' => $profile->business_name,
            'availabilityStatus' => $profile->availability_status->value,
            'verificationStatus' => $profile->verification_status->value,
            'subscriptionStatus' => $profile->subscription_status->value,
            'location' => $this->locationLabel($profile),
            'servicesCount' => $profile->services->count(),
        ];
    }

    /**
     * @return array{id: int, title: string, description: string|null, startingPrice: string|null, currencyCode: string, category: array{id: int, name: string}}
     */
    private function servicePayload(ArtisanService $service): array
    {
        $category = $service->category()->firstOrFail();

        return [
            'id' => $service->id,
            'title' => $service->title,
            'description' => $service->description,
            'startingPrice' => $service->starting_price,
            'currencyCode' => $service->currency_code,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ];
    }

    private function isVisibleMarketplaceProfile(ArtisanProfile $profile): bool
    {
        return $profile->verification_status === ArtisanVerificationStatus::Approved
            && $profile->subscription_status === ArtisanSubscriptionStatus::Active
            && $profile->availability_status !== ArtisanAvailabilityStatus::Vacation
            && $profile->is_public
            && $profile->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '>', now())
                ->exists();
    }

    private function locationLabel(ArtisanProfile $profile): string
    {
        return collect([
            $profile->territory?->name,
            $profile->localGovernment?->name,
            $profile->state?->name,
        ])->filter()->implode(', ');
    }
}
