<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\GenerateBriefing;
use App\Enums\Queue;
use App\Models\BriefingSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateScheduledBriefings implements ShouldQueue
{
    use Queueable;

    private const int CHUNK_SIZE = 50;

    private const int MAX_SUBSCRIPTIONS = 500;

    /** Create a new job instance. */
    public function __construct()
    {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /** Execute the job. */
    public function handle(GenerateBriefing $generateBriefing): void
    {
        $processed = 0;

        BriefingSubscription::query()
            ->with(['workspace', 'briefing', 'user'])
            ->due()
            ->orderBy('next_scheduled_at')
            ->limit(self::MAX_SUBSCRIPTIONS)
            ->chunkById(self::CHUNK_SIZE, function (Collection $subscriptions) use ($generateBriefing, &$processed): void {
                Log::info('Processing scheduled briefings chunk', [
                    'chunk_size' => $subscriptions->count(),
                    'processed_so_far' => $processed,
                ]);

                foreach ($subscriptions as $subscription) {
                    $this->processSubscription($subscription, $generateBriefing);
                    $processed++;
                }
            });

        Log::info('Completed processing scheduled briefings', [
            'total_processed' => $processed,
        ]);
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
