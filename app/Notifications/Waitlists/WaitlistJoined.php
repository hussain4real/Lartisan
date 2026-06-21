<?php

namespace App\Notifications\Waitlists;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistJoined extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public WaitlistEntry $entry)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You are on the Lartisan waitlist'))
            ->greeting(__('Hi :name,', ['name' => $this->entry->name]))
            ->line(__('Thanks for joining the Lartisan waitlist. We have saved your interest and will share launch updates as we open access.'))
            ->line(__('You joined as: :audience.', ['audience' => $this->entry->audience_type->label()]))
            ->line(__('We will contact you at :email when your invitation is ready.', ['email' => $this->entry->email]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'waitlist_entry_id' => $this->entry->id,
            'email' => $this->entry->email,
            'audience_type' => $this->entry->audience_type->value,
        ];
    }
}
