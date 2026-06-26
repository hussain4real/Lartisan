<?php

namespace App\Enums;

enum BookingMessageSenderRole: string
{
    case Customer = 'customer';
    case Artisan = 'artisan';
}
