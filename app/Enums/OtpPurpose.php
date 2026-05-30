<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case PhoneVerification = 'phone_verification';
    case AccountClaim = 'account_claim';
    case BookingGuest = 'booking_guest';
}
