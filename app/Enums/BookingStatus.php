<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
