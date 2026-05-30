<?php

namespace App\Http\Controllers\Artisan;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    use ResolvesCurrentArtisanProfile;

    public function show(Request $request): Response
    {
        $profile = $this->artisanProfileFrom($request);

        Gate::authorize('viewWallet', $profile);

        $wallet = $profile->wallet()->first();

        return Inertia::render('artisan/Wallet', [
            'profile' => [
                'id' => $profile->id,
                'businessName' => $profile->business_name,
            ],
            'wallet' => $this->walletPayload($wallet),
            'ledgerEntries' => $wallet instanceof Wallet
                ? $wallet->ledgerEntries()
                    ->latest('posted_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (WalletLedgerEntry $entry): array => $this->ledgerEntryPayload($entry))
                    ->all()
                : [],
            'payoutAccounts' => $profile->payoutAccounts()
                ->latest('id')
                ->get()
                ->map(fn (PayoutAccount $account): array => $this->payoutAccountPayload($account))
                ->all(),
        ]);
    }

    /**
     * @return array{id: int|null, currencyCode: string, availableBalance: int, pendingBalance: int, availableDisplay: string, pendingDisplay: string}
     */
    private function walletPayload(?Wallet $wallet): array
    {
        if (! $wallet instanceof Wallet) {
            return [
                'id' => null,
                'currencyCode' => 'NGN',
                'availableBalance' => 0,
                'pendingBalance' => 0,
                'availableDisplay' => number_format(0, 2),
                'pendingDisplay' => number_format(0, 2),
            ];
        }

        return [
            'id' => $wallet->id,
            'currencyCode' => $wallet->currency_code,
            'availableBalance' => $wallet->available_balance,
            'pendingBalance' => $wallet->pending_balance,
            'availableDisplay' => number_format($wallet->available_balance / 100, 2),
            'pendingDisplay' => number_format($wallet->pending_balance / 100, 2),
        ];
    }

    /**
     * @return array{id: int, type: string, direction: string, amount: int, amountDisplay: string, availableBalanceAfter: int, pendingBalanceAfter: int, immutableReference: string, description: string|null, postedAt: string|null}
     */
    private function ledgerEntryPayload(WalletLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type->value,
            'direction' => $entry->direction->value,
            'amount' => $entry->amount,
            'amountDisplay' => number_format($entry->amount / 100, 2),
            'availableBalanceAfter' => $entry->available_balance_after,
            'pendingBalanceAfter' => $entry->pending_balance_after,
            'immutableReference' => $entry->immutable_reference,
            'description' => $entry->description,
            'postedAt' => $entry->posted_at->toISOString(),
        ];
    }

    /**
     * @return array{id: int, provider: string, bankName: string, accountName: string, recipientCode: string|null, status: string, verifiedAt: string|null}
     */
    private function payoutAccountPayload(PayoutAccount $account): array
    {
        return [
            'id' => $account->id,
            'provider' => $account->provider->value,
            'bankName' => $account->bank_name,
            'accountName' => $account->account_name,
            'recipientCode' => $account->recipient_code,
            'status' => $account->status->value,
            'verifiedAt' => $account->verified_at?->toISOString(),
        ];
    }
}
