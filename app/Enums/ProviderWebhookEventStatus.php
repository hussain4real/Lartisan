<?php

namespace App\Enums;

enum ProviderWebhookEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
}
