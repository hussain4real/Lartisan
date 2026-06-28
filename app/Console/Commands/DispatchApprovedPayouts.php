<?php

namespace App\Console\Commands;

use App\Actions\Payouts\DispatchPayoutTransfer;
use App\Enums\PayoutBatchStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('payouts:dispatch-approved {--limit= : Maximum payouts to include in this batch}')]
#[Description('Dispatch approved and retrying payouts through the configured payout provider')]
class DispatchApprovedPayouts extends Command
{
    public function handle(DispatchPayoutTransfer $dispatchPayoutTransfer): int
    {
        $limit = $this->limit();
        $payouts = Payout::query()
            ->with(['payoutAccount', 'wallet'])
            ->whereIn('status', [PayoutStatus::Approved, PayoutStatus::Retrying])
            ->where(function ($query): void {
                $query
                    ->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->oldest('requested_at')
            ->limit($limit)
            ->get();

        if ($payouts->isEmpty()) {
            $this->info('No approved payouts are eligible for dispatch.');

            return self::SUCCESS;
        }

        $batch = PayoutBatch::query()->create([
            'status' => PayoutBatchStatus::Processing,
            'scheduled_for' => now(),
            'started_at' => now(),
            'total_payouts' => $payouts->count(),
            'total_amount' => $payouts->sum('amount'),
            'currency_code' => 'NGN',
            'metadata' => ['source' => 'payouts:dispatch-approved'],
        ]);

        foreach ($payouts as $payout) {
            try {
                $dispatchPayoutTransfer->handle($payout, batch: $batch);
            } catch (Throwable $throwable) {
                report($throwable);
                $this->error('Payout #'.$payout->id.' could not be dispatched: '.$throwable->getMessage());
            }
        }

        $this->completeBatch($batch);
        $this->info('Processed payout batch #'.$batch->id.'.');

        return self::SUCCESS;
    }

    private function completeBatch(PayoutBatch $batch): void
    {
        $batch->refresh();

        $successful = $batch->payouts()
            ->whereIn('status', [PayoutStatus::Processing, PayoutStatus::Paid])
            ->count();
        $failed = $batch->payouts()
            ->where('status', PayoutStatus::Failed)
            ->count();
        $actionRequired = $batch->payouts()
            ->where('status', PayoutStatus::InReview)
            ->count();

        $batch->forceFill([
            'action_required_payouts' => $actionRequired,
            'completed_at' => now(),
            'failed_payouts' => $failed,
            'status' => ($failed + $actionRequired) > 0
                ? PayoutBatchStatus::CompletedWithExceptions
                : PayoutBatchStatus::Completed,
            'successful_payouts' => $successful,
        ])->save();
    }

    private function limit(): int
    {
        $configured = config('lartisan.payouts.batch_limit', 100);
        $option = $this->option('limit');

        if (is_numeric($option)) {
            return max(1, (int) $option);
        }

        return max(1, is_numeric($configured) ? (int) $configured : 100);
    }
}
