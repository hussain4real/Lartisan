<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Escrowed = 'escrowed';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Confirmed = 'confirmed';
    case Settled = 'settled';
    case Refunded = 'refunded';
    case Reviewed = 'reviewed';
    case Cancelled = 'cancelled';
}
