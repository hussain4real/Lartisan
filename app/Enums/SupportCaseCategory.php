<?php

namespace App\Enums;

enum SupportCaseCategory: string
{
    case Booking = 'booking';
    case Payment = 'payment';
    case Payout = 'payout';
    case Dispute = 'dispute';
    case Safety = 'safety';
    case General = 'general';
}
