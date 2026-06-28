<?php

namespace App\Contracts\Notifications;

use App\Models\NotificationDelivery;
use App\Support\Notifications\NotificationProviderReceipt;

interface NotificationProvider
{
    public function enabled(): bool;

    public function providerName(): string;

    public function send(NotificationDelivery $delivery): NotificationProviderReceipt;
}
