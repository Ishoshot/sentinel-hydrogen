<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Jobs\Briefings\DeliverBriefing;
use App\Models\BriefingSubscription;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\BriefingParameterValidator;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class ProcessScheduledBriefings
{
    private const int CHUNK_SIZE = 50;

    private const int MAX_SUBSCRIPTIONS = 500;

    /**
     * Create a new action instance.
     */
    public function __construct(
        private GenerateBriefing $generateBriefing,
        private BriefingLimitEnforcer $limitEnforcer,
        private BriefingParameterValidator $parameterValidator,
    ) {}

    /**
     * Process all due briefing subscriptions.
     */
    public function handle(): void
    {
        $processed = 0;

        BriefingSubscription::query()
            ->with(['workspace', 'briefing', 'user'])
            ->due()
            ->orderBy('next_scheduled_at')
            ->limit(self::MAX_SUBSCRIPTIONS)
            ->chunkById(self::CHUNK_SIZE, function (Collection $subscriptions) use (&$processed): void {
                Log::info('Processing scheduled briefings chunk', [
                    'chunk_size' => $subscriptions->count(),
                    'processed_so_far' => $processed,
                ]);

                foreach ($subscriptions as $subscription) {
                    $this->processSubscription($subscription);
                    $processed++;
                }
            });

        Log::info('Completed processing scheduled briefings', [
            'total_processed' => $processed,
        ]);
    }

    /**
     * Process a single scheduled subscription.
     */
    private function processSubscription(BriefingSubscription $subscription): void
    {
        if ($subscription->workspace === null || $subscription->briefing === null || $subscription->user === null) {
            Log::warning('Skipping subscription with missing relations', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        try {
            $parameters = $this->parameterValidator->validate(
                $subscription->briefing,
                $subscription->parameters ?? [],
            );

            $canGenerate = $this->limitEnforcer->canGenerate($subscription->workspace, $subscription->briefing, $parameters);

            if ($canGenerate->isDenied()) {
                $this->handleDeniedSubscription($subscription, $canGenerate);

                return;
            }

            $generation = $this->generateBriefing->handle(
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
     * Handle a subscription denied by limits.
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
     * Determine if denied subscriptions should be deferred.
     */
    private function shouldDeferDeniedSubscription(BriefingLimitResult $result): bool
    {
        $reason = $result->reason ?? '';

        return $reason === '' || ! str_contains($reason, 'currently generating');
    }
}
