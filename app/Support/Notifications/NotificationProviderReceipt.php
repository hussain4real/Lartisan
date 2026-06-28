<?php

namespace App\Support\Notifications;

class NotificationProviderReceipt
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $status,
        public readonly array $metadata = [],
    ) {}
}
