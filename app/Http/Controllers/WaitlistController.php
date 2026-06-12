<?php

namespace App\Http\Controllers;

use App\Enums\WaitlistAudienceType;
use App\Http\Requests\StoreWaitlistEntryRequest;
use App\Models\Country;
use App\Models\LocalGovernment;
use App\Models\ServiceCategory;
use App\Models\State;
use App\Models\Territory;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaitlistController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Waitlist', [
            'audienceTypes' => collect(WaitlistAudienceType::cases())
                ->map(fn (WaitlistAudienceType $audienceType): array => [
                    'value' => $audienceType->value,
                    'label' => $audienceType->label(),
                ])
                ->all(),
            'geography' => $this->geographyPayload(),
            'serviceCategories' => $this->serviceCategoryPayload(),
            'joined' => (bool) $request->session()->get('waitlist.joined', false),
            'joinedEmail' => $request->session()->get('waitlist.email'),
        ]);
    }

    public function store(StoreWaitlistEntryRequest $request): RedirectResponse
    {
        $serviceCategory = $request->serviceCategory();
        $territory = $request->territory();

        WaitlistEntry::query()->updateOrCreate(
            ['email' => $request->email()],
            [
                'name' => $request->entryName(),
                'phone' => $request->phone(),
                'audience_type' => $request->audienceType(),
                'business_name' => $request->businessName(),
                'service_category_id' => $serviceCategory?->id,
                'country_id' => $request->country()->id,
                'state_id' => $request->state()->id,
                'local_government_id' => $request->localGovernment()->id,
                'territory_id' => $territory?->id,
                'note' => $request->note(),
                'contact_consent' => true,
            ],
        );

        return redirect('/waitlist')
            ->with('waitlist.joined', true)
            ->with('waitlist.email', $request->email());
    }

    /**
     * @return array{countries: array<int, array{id: int, name: string, isoCode: string, states: array<int, array{id: int, name: string, slug: string, localGovernments: array<int, array{id: int, name: string, slug: string, territories: array<int, array{id: int, name: string, slug: string, type: string}>}>}>}>}
     */
    private function geographyPayload(): array
    {
        return [
            'countries' => Country::query()
                ->where('active', true)
                ->with(['states.localGovernments.territories'])
                ->orderBy('name')
                ->get()
                ->map(fn (Country $country): array => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'isoCode' => $country->iso_code,
                    'states' => $country->states
                        ->where('active', true)
                        ->sortBy('name')
                        ->values()
                        ->map(fn (State $state): array => [
                            'id' => $state->id,
                            'name' => $state->name,
                            'slug' => $state->slug,
                            'localGovernments' => $state->localGovernments
                                ->where('active', true)
                                ->sortBy('name')
                                ->values()
                                ->map(fn (LocalGovernment $localGovernment): array => [
                                    'id' => $localGovernment->id,
                                    'name' => $localGovernment->name,
                                    'slug' => $localGovernment->slug,
                                    'territories' => $localGovernment->territories
                                        ->where('active', true)
                                        ->sortBy('name')
                                        ->values()
                                        ->map(fn (Territory $territory): array => [
                                            'id' => $territory->id,
                                            'name' => $territory->name,
                                            'slug' => $territory->slug,
                                            'type' => $territory->type->value,
                                        ])
                                        ->all(),
                                ])
                                ->all(),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function serviceCategoryPayload(): array
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
}
