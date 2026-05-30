<?php

namespace App\Enums;

enum WalletLedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
