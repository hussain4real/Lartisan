<?php

namespace App\Enums;

enum PlatformRole: string
{
    case SuperAdmin = 'super-admin';
    case StateCoordinator = 'state-coordinator';
    case LocalGovernmentAdmin = 'local-government-admin';
    case AreaAgent = 'area-agent';
    case Artisan = 'artisan';
    case Customer = 'customer';
    case GuestCustomer = 'guest-customer';
}
