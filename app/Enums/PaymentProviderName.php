<?php

namespace App\Enums;

enum PaymentProviderName: string
{
    case Paystack = 'paystack';
    case Flutterwave = 'flutterwave';
}
