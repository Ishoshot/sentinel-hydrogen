<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Enums\BriefingDeliveryChannel;
use App\Enums\Queue;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Notifications\Briefings\BriefingDeliveryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DeliverBriefing implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  BriefingGeneration  $generation  The generation to deliver
     * @param  string  $channel  The delivery channel
     * @param  BriefingSubscription  $subscription  The subscription
     */
    public function __construct(
        public BriefingGeneration $generation,
        public string $channel,
        public BriefingSubscription $subscription,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $channel = BriefingDeliveryChannel::tryFrom($this->channel);

        if ($channel === null) {
            Log::warning('Unknown delivery channel', [
                'channel' => $this->channel,
                'generation_id' => $this->generation->id,
            ]);

            return;
        }

        match ($channel) {
            BriefingDeliveryChannel::Email => $this->deliverViaEmail(),
            BriefingDeliveryChannel::Slack => $this->deliverViaSlack(),
            BriefingDeliveryChannel::Push => $this->deliverViaPush(),
        };
    }

    /**
     * Deliver the briefing via email.
     */
    private function deliverViaEmail(): void
    {
        $this->subscription->loadMissing('user');
        $user = $this->subscription->user;

        if ($user === null || $user->email === null) {
            Log::warning('Cannot deliver briefing via email - no user or email', [
                'subscription_id' => $this->subscription->id,
            ]);

            return;
        }

        try {
            $user->notify(new BriefingDeliveryNotification($this->generation));

            Log::info('Briefing delivered via email', [
                'generation_id' => $this->generation->id,
                'user_email' => $user->email,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to deliver briefing via email', [
                'generation_id' => $this->generation->id,
                'user_email' => $user->email,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    /**
     * Deliver the briefing via Slack.
     */
    private function deliverViaSlack(): void
    {
        $webhookUrl = $this->subscription->slack_webhook_url;

        if ($webhookUrl === null) {
            Log::warning('Cannot deliver briefing via Slack - no webhook URL', [
                'subscription_id' => $this->subscription->id,
            ]);

            return;
        }

        $this->generation->loadMissing('briefing');

        $excerpt = $this->generation->excerpts['slack'] ?? $this->generation->excerpts['short'] ?? null;

        if ($excerpt === null) {
            Log::warning('Cannot deliver briefing via Slack - no excerpt available', [
                'generation_id' => $this->generation->id,
            ]);

            return;
        }

        try {
            Http::post($webhookUrl, [
                'text' => $this->generation->briefing?->title ?? 'Briefing Ready',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $excerpt,
                        ],
                    ],
                ],
            ]);

            Log::info('Briefing delivered via Slack', [
                'generation_id' => $this->generation->id,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to deliver briefing via Slack', [
                'generation_id' => $this->generation->id,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    /**
     * Deliver the briefing via push notification.
     */
    private function deliverViaPush(): void
    {
        // TODO: Implement push notification delivery
        Log::info('Briefing push notification delivery queued', [
            'generation_id' => $this->generation->id,
            'subscription_id' => $this->subscription->id,
        ]);
    }
}
