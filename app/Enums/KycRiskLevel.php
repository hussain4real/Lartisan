<?php

namespace App\Enums;

enum KycRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
