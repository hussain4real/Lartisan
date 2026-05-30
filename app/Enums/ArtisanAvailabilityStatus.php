<?php

namespace App\Enums;

enum ArtisanAvailabilityStatus: string
{
    case Online = 'online';
    case Busy = 'busy';
    case Offline = 'offline';
    case Vacation = 'vacation';
}
