<?php

namespace App\Http\Controllers\Artisan;

use App\Actions\Payouts\RequestPayout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artisan\RequestPayoutRequest;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PayoutController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function store(
        RequestPayoutRequest $request,
        string $currentTeam,
        RequestPayout $requestPayout,
    ): RedirectResponse {
        $profile = $this->artisanProfileFrom($request);
        Gate::authorize('viewWallet', $profile);
        $user = $request->user();
        assert($user instanceof User);

        $payoutAccount = PayoutAccount::query()
            ->where('artisan_profile_id', $profile->id)
            ->findOrFail($request->integer('payout_account_id'));
        $amount = (int) round($request->float('amount') * 100);

        $requestPayout->handle(
            profile: $profile,
            payoutAccount: $payoutAccount,
            requester: $user,
            amount: $amount,
            metadata: ['notes' => $request->string('notes')->trim()->toString() ?: null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout requested.')]);

        return to_route('artisan.wallet.show', ['current_team' => $profile->team()->firstOrFail()->slug]);
    }
}
