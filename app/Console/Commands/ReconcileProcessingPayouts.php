<?php

namespace App\Console\Commands;

use App\Actions\Payouts\CreatePayoutExceptionCase;
use App\Actions\Payouts\ReconcilePayoutTransfer;
use App\Contracts\Payouts\PayoutProvider;
use App\Enums\PayoutAttemptStatus;
use App\Enums\PayoutStatus;
use App\Enums\SupportCasePriority;
use App\Models\Payout;
use App\Support\Payouts\PayoutProviderException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payouts:reconcile-processing {--limit= : Maximum payouts to reconcile} {--stale-minutes= : Minimum processing age before polling}')]
#[Description('Poll provider status for stale processing payouts')]
class ReconcileProcessingPayouts extends Command
{
    public function handle(
        PayoutProvider $payoutProvider,
        ReconcilePayoutTransfer $reconcilePayoutTransfer,
        CreatePayoutExceptionCase $createPayoutExceptionCase,
    ): int {
        $payouts = Payout::query()
            ->where('status', PayoutStatus::Processing)
            ->whereNotNull('provider_reference')
            ->where(function ($query): void {
                $query
                    ->where('processing_at', '<=', now()->subMinutes($this->staleMinutes()))
                    ->orWhereHas('attempts', function ($query): void {
                        $query->where('status', PayoutAttemptStatus::Uncertain);
                    });
            })
            ->oldest('processing_at')
            ->limit($this->limit())
            ->get();

        if ($payouts->isEmpty()) {
            $this->info('No processing payouts are stale enough for reconciliation.');

            return self::SUCCESS;
        }

        foreach ($payouts as $payout) {
            try {
                $verification = $payoutProvider->verifyTransfer((string) $payout->provider_reference);
                $reconcilePayoutTransfer->handle($payout, $verification);
            } catch (PayoutProviderException $exception) {
                $createPayoutExceptionCase->handle(
                    payout: $payout,
                    subject: 'Payout transfer needs reconciliation',
                    description: $exception->getMessage(),
                    priority: SupportCasePriority::Normal,
                    metadata: ['exception' => $exception->context],
                );
                $this->error('Payout #'.$payout->id.' could not be verified: '.$exception->getMessage());
            }
        }

        $this->info('Reconciled '.$payouts->count().' processing payout(s).');

        return self::SUCCESS;
    }

    private function limit(): int
    {
        $configured = config('lartisan.payouts.reconciliation_limit', 100);
        $option = $this->option('limit');

        if (is_numeric($option)) {
            return max(1, (int) $option);
        }

        return max(1, is_numeric($configured) ? (int) $configured : 100);
    }

    private function staleMinutes(): int
    {
        $configured = config('lartisan.payouts.stale_processing_minutes', 30);
        $option = $this->option('stale-minutes');

        if (is_numeric($option)) {
            return max(1, (int) $option);
        }

        return max(1, is_numeric($configured) ? (int) $configured : 30);
    }
}
