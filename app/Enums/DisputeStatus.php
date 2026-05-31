<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case UnderLocalGovernmentReview = 'under_lga_review';
    case EscalatedToState = 'escalated_to_state';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
