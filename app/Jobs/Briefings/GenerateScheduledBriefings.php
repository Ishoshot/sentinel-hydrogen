<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\GenerateBriefing;
use App\Enums\Queue;
use App\Models\BriefingSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateScheduledBriefings implements ShouldQueue
{
    use Queueable;

    /** Create a new job instance. */
    public function __construct()
    {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /** Execute the job. */
    public function handle(GenerateBriefing $generateBriefing): void
    {
        $subscriptions = BriefingSubscription::query()
            ->with(['workspace', 'briefing', 'user'])
            ->due()
            ->get();

        Log::info('Processing scheduled briefings', [
            'subscription_count' => $subscriptions->count(),
        ]);

        foreach ($subscriptions as $subscription) {
            $this->processSubscription($subscription, $generateBriefing);
        }
    }

    /** Process a single subscription. */
    private function processSubscription(
        BriefingSubscription $subscription,
        GenerateBriefing $generateBriefing,
    ): void {
        if ($subscription->workspace === null || $subscription->briefing === null || $subscription->user === null) {
            Log::warning('Skipping subscription with missing relations', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        try {
            $generation = $generateBriefing->handle(
                workspace: $subscription->workspace,
                briefing: $subscription->briefing,
                user: $subscription->user,
                parameters: $subscription->parameters ?? [],
            );

            $subscription->markGenerated();

            foreach ($subscription->delivery_channels ?? [] as $channel) {
                DeliverBriefing::dispatch($generation, $channel, $subscription);
            }

            Log::info('Scheduled briefing generated', [
                'subscription_id' => $subscription->id,
                'generation_id' => $generation->id,
                'workspace_id' => $subscription->workspace_id,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to generate scheduled briefing', [
                'subscription_id' => $subscription->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
