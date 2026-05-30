<?php

namespace App\Enums;

enum ReasonCodeCategory: string
{
    case KycDecision = 'kyc_decision';
    case TerritoryAssignment = 'territory_assignment';
    case Suspension = 'suspension';
}
