<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TeamInvitationModel $invitation)
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
        $team = $this->invitation->team()->firstOrFail();
        $inviter = $this->invitation->inviter()->firstOrFail();

        return (new MailMessage)
            ->subject(__('Join :teamName on Lartisan', ['teamName' => $team->name]))
            ->greeting(__('You have been invited to Lartisan'))
            ->line(__(':inviterName invited you to join :teamName on Lartisan.', [
                'inviterName' => $inviter->name,
                'teamName' => $team->name,
            ]))
            ->line(__('Accept the invitation to collaborate inside this artisan business workspace.'))
            ->action(__('Accept invitation'), route('invitations.accept', $this->invitation));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team()->firstOrFail()->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
