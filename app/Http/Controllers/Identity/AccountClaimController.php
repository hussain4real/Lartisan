<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\ClaimAgentCreatedAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ClaimAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountClaimController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('auth/ClaimAccount', [
            'token' => (string) $request->query('token', ''),
        ]);
    }

    public function store(
        ClaimAccountRequest $request,
        ClaimAgentCreatedAccount $claimAgentCreatedAccount,
    ): RedirectResponse {
        $user = $claimAgentCreatedAccount->handle(
            token: $request->token(),
            password: $request->password(),
            name: $request->accountName(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        $currentTeam = $user->currentTeam;

        if ($currentTeam !== null) {
            return to_route('dashboard', ['current_team' => $currentTeam]);
        }

        return to_route('home');
    }
}
