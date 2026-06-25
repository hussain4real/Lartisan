<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationProvider;
use App\Models\NotificationDelivery;
use App\Notifications\TransactionalNotification;
use App\Support\Notifications\NotificationProviderReceipt;
use Illuminate\Support\Facades\Notification;

class MailNotificationProvider implements NotificationProvider
{
    public function enabled(): bool
    {
        return (bool) config('lartisan.notifications.channels.email.enabled', true);
    }

    public function providerName(): string
    {
        return 'mail';
    }

    public function send(NotificationDelivery $delivery): NotificationProviderReceipt
    {
        Notification::route('mail', $delivery->recipient_address)
            ->notify(new TransactionalNotification($delivery));

        return new NotificationProviderReceipt(
            messageId: 'mail-'.$delivery->id,
            status: 'queued',
        );
    }
}
