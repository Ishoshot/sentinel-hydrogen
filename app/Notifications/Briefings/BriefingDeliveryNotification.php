<?php

declare(strict_types=1);

namespace App\Notifications\Briefings;

use App\Models\BriefingGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BriefingDeliveryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public BriefingGeneration $generation,
    ) {}

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
        $this->generation->loadMissing('briefing', 'workspace');

        $subject = sprintf(
            '%s - %s',
            $this->generation->briefing?->title ?? 'Briefing',
            $this->generation->workspace?->name ?? 'Sentinel'
        );

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.briefings.delivery', [
                'generation' => $this->generation,
                'briefing' => $this->generation->briefing,
                'workspace' => $this->generation->workspace,
                'narrative' => $this->generation->narrative,
                'achievements' => $this->generation->achievements ?? [],
                'excerpts' => $this->generation->excerpts ?? [],
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'briefing_delivery',
            'generation_id' => $this->generation->id,
            'briefing_id' => $this->generation->briefing_id,
        ];
    }
}
