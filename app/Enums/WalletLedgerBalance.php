<?php

namespace App\Enums;

enum WalletLedgerBalance: string
{
    case Available = 'available';
    case Pending = 'pending';
}
