<?php

namespace App\Actions\Payouts;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\SendLifecycleNotification;
use App\Actions\Payments\PostWalletLedgerEntry;
use App\Enums\PayoutStatus;
use App\Enums\PlatformPermission;
use App\Enums\WalletLedgerDirection;
use App\Enums\WalletLedgerEntryType;
use App\Models\ArtisanProfile;
use App\Models\Payout;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovePayout
{
    public function __construct(
        private readonly PostWalletLedgerEntry $postWalletLedgerEntry,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly SendLifecycleNotification $sendLifecycleNotification,
    ) {}

    public function handle(Payout $payout, User $approver): Payout
    {
        $updatedPayout = DB::transaction(function () use ($payout, $approver): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->authorize($lockedPayout, $approver);

            if (! in_array($lockedPayout->status, [PayoutStatus::Pending, PayoutStatus::InReview], true)) {
                throw new InvalidArgumentException('Only pending payouts can be approved.');
            }

            $wallet = $lockedPayout->wallet()->lockForUpdate()->firstOrFail();
            if ($wallet->available_balance < $lockedPayout->amount) {
                throw new InvalidArgumentException('Wallet balance is insufficient for this payout.');
            }

            $existingLedgerEntry = WalletLedgerEntry::query()
                ->where('source_type', $lockedPayout->getMorphClass())
                ->where('source_id', $lockedPayout->id)
                ->where('type', WalletLedgerEntryType::PayoutDebit)
                ->first();

            if (! $existingLedgerEntry instanceof WalletLedgerEntry) {
                $this->postWalletLedgerEntry->handle(
                    wallet: $wallet,
                    type: WalletLedgerEntryType::PayoutDebit,
                    direction: WalletLedgerDirection::Debit,
                    amount: $lockedPayout->amount,
                    source: $lockedPayout,
                    immutableReference: 'payout-'.$lockedPayout->id.'-debit',
                    description: 'Approved payout debit',
                    metadata: ['payout_account_id' => $lockedPayout->payout_account_id],
                );
            }

            $before = ['status' => $lockedPayout->status->value];
            $lockedPayout->forceFill([
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'status' => PayoutStatus::Approved,
            ])->save();

            $this->recordAuditLog->handle(
                actor: $approver,
                action: 'payout.approved',
                subject: $lockedPayout,
                before: $before,
                after: ['status' => $lockedPayout->status->value],
            );

            return $lockedPayout->refresh();
        }, attempts: 3);

        $this->sendLifecycleNotification->payoutStatusChanged($updatedPayout);

        return $updatedPayout->refresh();
    }

    private function authorize(Payout $payout, User $approver): void
    {
        $profile = $payout->artisanProfile()->firstOrFail();

        if ($approver->can(PlatformPermission::ManagePayouts->value)
            && ArtisanProfile::query()->whereKey($profile->id)->visibleTo($approver)->exists()) {
            return;
        }

        throw new AuthorizationException('You cannot approve this payout.');
    }
}
