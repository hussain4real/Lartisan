<?php

namespace App\Enums;

enum ArtisanServiceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Hidden = 'hidden';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
