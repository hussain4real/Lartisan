<?php

namespace App\Enums;

enum SubscriptionInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';
}
