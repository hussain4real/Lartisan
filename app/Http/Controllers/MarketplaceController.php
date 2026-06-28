<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\EnsureBookingOtpVerified;
use App\Actions\Bookings\SearchArtisans;
use App\Enums\ArtisanAvailabilityStatus;
use App\Enums\ArtisanServiceStatus;
use App\Enums\ArtisanSubscriptionStatus;
use App\Enums\ArtisanVerificationStatus;
use App\Enums\PreferredChannel;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Marketplace\SearchArtisansRequest;
use App\Http\Requests\Marketplace\StoreBookingRequest;
use App\Models\Address;
use App\Models\ArtisanProfile;
use App\Models\ArtisanService;
use App\Models\CustomerProfile;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MarketplaceController extends Controller
{
    private const MARKETPLACE_ARTISANS_PER_PAGE = 12;

    public function index(SearchArtisansRequest $request, SearchArtisans $searchArtisans): Response
    {
        $queryText = $request->queryText();
        $category = $request->category();
        $state = $request->state();
        $localGovernment = $request->localGovernment();
        $territory = $request->territory();

        return Inertia::render('marketplace/Index', [
            'filters' => [
                'query' => $queryText,
                'serviceCategoryId' => $category?->id,
                'stateId' => $state?->id,
                'localGovernmentId' => $localGovernment?->id,
                'territoryId' => $territory?->id,
            ],
            'categories' => $this->categoryOptions(),
            'states' => $this->stateOptions(),
            'artisans' => Inertia::scroll(fn () => $searchArtisans->paginate(
                query: $queryText,
                category: $category,
                state: $state,
                localGovernment: $localGovernment,
                territory: $territory,
                perPage: self::MARKETPLACE_ARTISANS_PER_PAGE,
                page: $request->page(),
                path: route('marketplace.index'),
                queryParameters: Arr::except($request->query(), ['page']),
            )->through(fn (ArtisanProfile $profile): array => $this->artisanCardPayload($profile))),
        ]);
    }

    public function show(Request $request, ArtisanProfile $artisanProfile): Response
    {
        abort_unless($this->isVisibleMarketplaceProfile($artisanProfile), 404);

        return Inertia::render('marketplace/Show', [
            'artisan' => $this->artisanDetailPayload(
                $artisanProfile->load(['services.category', 'state', 'localGovernment', 'territory']),
                $request->user(),
            ),
        ]);
    }

    public function create(Request $request, ArtisanProfile $artisanProfile): Response
    {
        abort_unless($this->isVisibleMarketplaceProfile($artisanProfile), 404);

        $user = $request->user();

        return Inertia::render('marketplace/Book', [
            'artisan' => $this->artisanDetailPayload(
                $artisanProfile->load(['services.category', 'state', 'localGovernment', 'territory']),
                $user,
            ),
            'states' => $this->stateOptions(),
            'savedAddresses' => $user instanceof User ? $this->savedAddressOptions($user) : [],
            'customerDefaults' => $user instanceof User ? $this->customerDefaults($user) : [
                'name' => null,
                'email' => null,
                'phoneCountryCode' => '+234',
                'phoneNumber' => null,
                'preferredChannel' => PreferredChannel::Whatsapp->value,
                'defaultNotes' => null,
                'scheduleWindow' => 'flexible',
            ],
            'requiresOtp' => ! $user instanceof User || $user->phone_verified_at === null,
        ]);
    }

    public function store(
        StoreBookingRequest $request,
        ArtisanProfile $artisanProfile,
        CreateBooking $createBooking,
        EnsureBookingOtpVerified $ensureBookingOtpVerified,
    ): RedirectResponse {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $phone = $ensureBookingOtpVerified->handle(
            customer: $user,
            phoneCountryCode: $request->phoneCountryCode(),
            phoneNumber: $request->customerPhone(),
            code: $request->otpCode(),
        );

        $createdBooking = $createBooking->handle(
            profile: $artisanProfile,
            service: $request->service(),
            customer: $user,
            customerName: $request->customerName(),
            customerPhone: $phone['e164'],
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
     * @return array{id: int, businessName: string, publicSummary: string|null, availabilityStatus: string, verificationStatus: string, subscriptionStatus: string, yearsExperience: int|null, serviceRadiusKm: int|null, publicPhone: string|null, publicEmail: string|null, location: string, servicesCount: int, isFavorite: bool, services: array<int, array{id: int, title: string, description: string|null, startingPrice: string|null, currencyCode: string, category: array{id: int, name: string}}>, portfolio: array<int, array{id: int, name: string, url: string}>}
     */
    private function artisanDetailPayload(ArtisanProfile $profile, mixed $user = null): array
    {
        return [
            ...$this->artisanCardPayload($profile),
            'publicSummary' => $profile->public_summary,
            'yearsExperience' => $profile->years_experience,
            'serviceRadiusKm' => $profile->service_radius_km,
            'publicPhone' => $profile->public_phone,
            'publicEmail' => $profile->public_email,
            'isFavorite' => $user instanceof User
                && $user->customerFavorites()->where('artisan_profile_id', $profile->id)->exists(),
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

    /**
     * @return array<int, array{id: int, label: string, contactName: string|null, phone: string|null, line1: string, line2: string|null, landmark: string|null, countryId: int|null, stateId: int, localGovernmentId: int, territoryId: int|null, isDefault: bool}>
     */
    private function savedAddressOptions(User $user): array
    {
        return Address::query()
            ->ownedBy($user)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (Address $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'contactName' => $address->contact_name,
                'phone' => $address->phone,
                'line1' => $address->line_1,
                'line2' => $address->line_2,
                'landmark' => $address->landmark,
                'countryId' => $address->country_id,
                'stateId' => $address->state_id,
                'localGovernmentId' => $address->local_government_id,
                'territoryId' => $address->territory_id,
                'isDefault' => $address->is_default,
            ])
            ->all();
    }

    /**
     * @return array{name: string, email: string, phoneCountryCode: string, phoneNumber: string|null, preferredChannel: string, defaultNotes: string|null, scheduleWindow: string}
     */
    private function customerDefaults(User $user): array
    {
        $profile = $user->customerProfile()->first();
        $preferences = $profile instanceof CustomerProfile ? ($profile->preferences ?? []) : [];

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phoneCountryCode' => $user->phone_country_code ?? '+234',
            'phoneNumber' => $user->phone_number,
            'preferredChannel' => $user->preferred_channel instanceof PreferredChannel
                ? $user->preferred_channel->value
                : PreferredChannel::Whatsapp->value,
            'defaultNotes' => is_string($preferences['default_notes'] ?? null) ? $preferences['default_notes'] : null,
            'scheduleWindow' => is_string($preferences['schedule_window'] ?? null) ? $preferences['schedule_window'] : 'flexible',
        ];
    }
}
