<?php

namespace App\Enums;

enum PreferredChannel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case InApp = 'in_app';
}
