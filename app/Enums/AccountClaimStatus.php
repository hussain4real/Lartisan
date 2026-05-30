<?php

namespace App\Enums;

enum AccountClaimStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
