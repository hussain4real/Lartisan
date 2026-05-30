<?php

namespace App\Enums;

enum TeamKind: string
{
    case Personal = 'personal';
    case Workspace = 'workspace';
    case ArtisanBusiness = 'artisan-business';
}
