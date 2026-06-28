<?php

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Sent = 'sent';
    case Failed = 'failed';
    case Delivered = 'delivered';
    case Read = 'read';
    case DeadLettered = 'dead_lettered';
}
