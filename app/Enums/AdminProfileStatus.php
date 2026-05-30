<?php

namespace App\Enums;

enum AdminProfileStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
