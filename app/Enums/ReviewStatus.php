<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Published = 'published';
    case PendingModeration = 'pending_moderation';
    case Hidden = 'hidden';
    case Disputed = 'disputed';
}
