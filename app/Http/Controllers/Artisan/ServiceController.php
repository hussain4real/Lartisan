<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Artisans\CreateArtisanService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\StoreArtisanServiceRequest;
use App\Models\ArtisanService;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function index(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('view', $profile);
        Gate::authorize('viewAny', ArtisanService::class);

        return Inertia::render('artisan/Services', [
            'services' => $profile->services()
                ->with('category')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ArtisanService $service): array => $this->servicePayload($service))
                ->all(),
            'categories' => ServiceCategory::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ServiceCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->all(),
            'statuses' => ['draft', 'active', 'hidden'],
        ]);
    }

    public function store(
        StoreArtisanServiceRequest $request,
        CreateArtisanService $createArtisanService,
    ): RedirectResponse {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('create', [ArtisanService::class, $profile]);

        $createArtisanService->handle(
            profile: $profile,
            category: $request->category(),
            title: $request->title(),
            description: $request->description(),
            startingPrice: $request->startingPrice(),
            currencyCode: $request->currencyCode(),
            status: $request->serviceStatus(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service added.')]);

        return to_route('artisan.services.index', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }

    /**
     * @return array{id: int, title: string, description: string|null, startingPrice: string|null, currencyCode: string, status: string, category: array{id: int, name: string}}
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
            'status' => $service->status->value,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ];
    }
}
