<?php

namespace App\Enums;

enum OperationalHealthStatus: string
{
    case Passing = 'passing';
    case Warning = 'warning';
    case Failing = 'failing';
}
