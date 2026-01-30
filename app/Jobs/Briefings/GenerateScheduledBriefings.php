<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\GenerateBriefing;
use App\Enums\Queue\Queue;
use App\Models\BriefingSubscription;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\BriefingParameterValidator;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Generate briefings for all due scheduled subscriptions.
 */
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
    public function handle(
        GenerateBriefing $generateBriefing,
        BriefingLimitEnforcer $limitEnforcer,
        BriefingParameterValidator $parameterValidator,
    ): void {
        $processed = 0;

        BriefingSubscription::query()
            ->with(['workspace', 'briefing', 'user'])
            ->due()
            ->orderBy('next_scheduled_at')
            ->limit(self::MAX_SUBSCRIPTIONS)
            ->chunkById(self::CHUNK_SIZE, function (Collection $subscriptions) use ($generateBriefing, $limitEnforcer, $parameterValidator, &$processed): void {
                Log::info('Processing scheduled briefings chunk', [
                    'chunk_size' => $subscriptions->count(),
                    'processed_so_far' => $processed,
                ]);

                foreach ($subscriptions as $subscription) {
                    $this->processSubscription($subscription, $generateBriefing, $limitEnforcer, $parameterValidator);
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
        BriefingLimitEnforcer $limitEnforcer,
        BriefingParameterValidator $parameterValidator,
    ): void {
        if ($subscription->workspace === null || $subscription->briefing === null || $subscription->user === null) {
            Log::warning('Skipping subscription with missing relations', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        try {
            $parameters = $parameterValidator->validate(
                $subscription->briefing,
                $subscription->parameters ?? [],
            );

            $canGenerate = $limitEnforcer->canGenerate($subscription->workspace, $subscription->briefing, $parameters);

            if ($canGenerate->isDenied()) {
                $this->handleDeniedSubscription($subscription, $canGenerate);

                return;
            }

            $generation = $generateBriefing->handle(
                workspace: $subscription->workspace,
                briefing: $subscription->briefing,
                user: $subscription->user,
                parameters: $parameters,
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
        } catch (ValidationException $exception) {
            Log::warning('Scheduled briefing parameters failed validation', [
                'subscription_id' => $subscription->id,
                'errors' => $exception->errors(),
            ]);

            $subscription->markDeferred();
        } catch (Throwable $throwable) {
            Log::error('Failed to generate scheduled briefing', [
                'subscription_id' => $subscription->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Handle a subscription that was denied due to limits.
     */
    private function handleDeniedSubscription(BriefingSubscription $subscription, BriefingLimitResult $result): void
    {
        Log::warning('Scheduled briefing blocked by limits', [
            'subscription_id' => $subscription->id,
            'reason' => $result->reason,
        ]);

        if ($this->shouldDeferDeniedSubscription($result)) {
            $subscription->markDeferred();
        }
    }

    /**
     * Determine if a denied subscription should be deferred.
     */
    private function shouldDeferDeniedSubscription(BriefingLimitResult $result): bool
    {
        $reason = $result->reason ?? '';

        return $reason === '' || ! str_contains($reason, 'currently generating');
    }
}
