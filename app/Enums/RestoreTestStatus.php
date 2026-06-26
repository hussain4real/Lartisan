<?php

namespace App\Enums;

enum RestoreTestStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
}
