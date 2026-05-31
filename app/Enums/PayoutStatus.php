<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Cancelled = 'cancelled';
    case Adjusted = 'adjusted';
}
