<?php

namespace App\Enums;

enum WaitlistAudienceType: string
{
    case Customer = 'customer';
    case Artisan = 'artisan';
    case Operations = 'operations';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Artisan => 'Artisan',
            self::Operations => 'Operations',
        };
    }
}
