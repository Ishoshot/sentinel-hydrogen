<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvitationSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Invitation $invitation,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $workspaceName = $this->invitation->workspace?->name ?? 'a workspace';

        return (new MailMessage)
            ->subject("You're invited to join {$workspaceName}")
            ->markdown('mail.invitation-sent', [
                'invitation' => $this->invitation,
                'acceptUrl' => $this->getAcceptUrl(),
            ]);
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'workspace_id' => $this->invitation->workspace_id,
            'workspace_name' => $this->invitation->workspace?->name,
            'invited_by_name' => $this->invitation->invitedBy?->name,
            'role' => $this->invitation->role->value,
        ];
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(object $notifiable): string
    {
        return 'invitation.sent';
    }

    /**
     * Get the accept invitation URL.
     */
    private function getAcceptUrl(): string
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return $frontendUrl.'/invitations/'.$this->invitation->token;
    }
}
