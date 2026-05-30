<?php

namespace App\Enums;

enum ArtisanVerificationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case FieldCheckPending = 'field_check_pending';
    case FieldCheckComplete = 'field_check_complete';
    case LgaReview = 'lga_review';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Escalated = 'escalated';
    case Suspended = 'suspended';
}
