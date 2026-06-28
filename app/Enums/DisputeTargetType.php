<?php

namespace App\Enums;

enum DisputeTargetType: string
{
    case Booking = 'booking';
    case Review = 'review';
    case Profile = 'profile';
    case Payment = 'payment';
}
