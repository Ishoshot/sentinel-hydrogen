<?php

declare(strict_types=1);

namespace App\Services\Queue\Rules;

use App\Enums\Queue\Queue;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueRuleResult;

/**
 * Routes long-running jobs to dedicated queues.
 *
 * Jobs with estimated duration over 2 minutes should not block
 * latency-sensitive queues.
 */
final readonly class LongRunningJobRule implements QueueRule
{
    /**
     * Get the rule priority.
     */
    public function priority(): int
    {
        return 80;
    }

    /**
     * Check if this rule applies to the given context.
     */
    public function applies(JobContext $context): bool
    {
        return $context->isLongRunning();
    }

    /**
     * Evaluate the rule and return a result.
     */
    public function evaluate(JobContext $context): QueueRuleResult
    {
        $estimatedDuration = $context->estimatedDurationSeconds ?? 0;

        // Very long jobs (>5 min) go to bulk queue
        if ($estimatedDuration > 300) {
            return QueueRuleResult::force(
                Queue::Bulk,
                sprintf('Job with %ds estimated duration routed to bulk queue', $estimatedDuration)
            );
        }

        return QueueRuleResult::force(
            Queue::LongRunning,
            sprintf('Job with %ds estimated duration routed to long-running queue', $estimatedDuration)
        );
    }

    /**
     * Get the rule name.
     */
    public function name(): string
    {
        return 'long_running_job';
    }
}
