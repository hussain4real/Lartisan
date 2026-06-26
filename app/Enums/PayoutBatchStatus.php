<?php

namespace App\Enums;

enum PayoutBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithExceptions = 'completed_with_exceptions';
    case Failed = 'failed';
}
