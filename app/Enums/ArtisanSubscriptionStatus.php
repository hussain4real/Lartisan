<?php

namespace App\Enums;

enum ArtisanSubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';
}
