<?php

namespace App\Enums;

enum FieldVisitStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case NeedsRevisit = 'needs_revisit';
    case Cancelled = 'cancelled';
}
