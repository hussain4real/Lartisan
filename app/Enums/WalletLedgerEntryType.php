<?php

namespace App\Enums;

enum WalletLedgerEntryType: string
{
    case BookingCredit = 'booking_credit';
    case CommissionDebit = 'commission_debit';
    case FeeDebit = 'fee_debit';
    case PayoutDebit = 'payout_debit';
    case RefundDebit = 'refund_debit';
    case AdjustmentCredit = 'adjustment_credit';
    case AdjustmentDebit = 'adjustment_debit';
}
