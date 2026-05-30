<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Artisans\RecordFieldVisit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\RecordFieldVisitRequest;
use App\Models\FieldVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FieldVisitController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function store(
        RecordFieldVisitRequest $request,
        RecordFieldVisit $recordFieldVisit,
    ): RedirectResponse {
        $profile = $this->artisanProfileFrom($request);
        $user = $this->userFrom($request);
        $visitedAt = $request->visitedAt();

        Gate::authorize('create', [FieldVisit::class, $profile]);

        $recordFieldVisit->handle(
            profile: $profile,
            areaAgent: $user,
            submission: $request->kycSubmission(),
            territory: $request->territory(),
            status: $request->visitStatus(),
            visitedAt: $visitedAt === null ? null : Date::parse($visitedAt),
            latitude: $request->latitude(),
            longitude: $request->longitude(),
            notes: $request->notes(),
            checklist: $request->checklist(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field visit recorded.')]);

        return to_route('artisan.kyc.show', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }
}
