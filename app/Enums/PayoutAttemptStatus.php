<?php

namespace App\Enums;

enum PayoutAttemptStatus: string
{
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
}
