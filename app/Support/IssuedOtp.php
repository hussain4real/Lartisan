<?php

namespace App\Support;

use App\Models\OtpRecord;

readonly class IssuedOtp
{
    public function __construct(
        public OtpRecord $record,
        public string $plainCode,
    ) {}
}
