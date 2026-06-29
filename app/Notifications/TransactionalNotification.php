<?php

namespace App\Notifications;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly NotificationDelivery $delivery,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = trim((string) $this->delivery->recipient_name);

        return (new MailMessage)
            ->subject($this->delivery->subject)
            ->greeting($recipientName !== '' ? __('Hello :name,', ['name' => $recipientName]) : __('Hello,'))
            ->line($this->delivery->body)
            ->line(__('Open Lartisan to review the latest status and any next steps.'))
            ->action(__('Open Lartisan'), url('/'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'event_type' => $this->delivery->event_type->value,
            'source_id' => $this->delivery->source_id,
            'source_type' => $this->delivery->source_type,
        ];
    }
}
