<?php

namespace App\Contracts\Payouts;

use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\PayoutAttempt;
use App\Support\Payouts\BankAccountResolution;
use App\Support\Payouts\TransferDispatch;
use App\Support\Payouts\TransferRecipient;
use App\Support\Payouts\TransferVerification;

interface PayoutProvider
{
    public function resolveBankAccount(PayoutAccount $account): BankAccountResolution;

    public function createTransferRecipient(PayoutAccount $account): TransferRecipient;

    public function initiateTransfer(Payout $payout, PayoutAttempt $attempt): TransferDispatch;

    public function verifyTransfer(string $reference): TransferVerification;

    public function webhookSignatureIsValid(string $payload, ?string $signature): bool;
}
