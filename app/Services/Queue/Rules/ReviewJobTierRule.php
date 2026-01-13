<?php

declare(strict_types=1);

namespace App\Services\Queue\Rules;

use App\Enums\Queue;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\JobContext;
use App\Services\Queue\QueueRuleResult;

/**
 * Routes review jobs to tier-appropriate queues.
 *
 * Enterprise and paid customers get higher priority queues,
 * ensuring better latency for paying customers.
 */
final readonly class ReviewJobTierRule implements QueueRule
{
    /**
     * Review job classes that should be routed based on tier.
     *
     * @var array<int, class-string>
     */
    private const array REVIEW_JOB_CLASSES = [
        ExecuteReviewRun::class,
    ];

    /**
     * Get the rule priority.
     */
    public function priority(): int
    {
        return 20;
    }

    /**
     * Check if this rule applies to the given context.
     */
    public function applies(JobContext $context): bool
    {
        return in_array($context->jobClass, self::REVIEW_JOB_CLASSES, true);
    }

    /**
     * Evaluate the rule and return a result.
     */
    public function evaluate(JobContext $context): QueueRuleResult
    {
        if (! $context->isPriorityQueueEnabled()) {
            $tierLabel = $context->tier ?? 'free';

            return QueueRuleResult::force(
                Queue::ReviewsDefault,
                sprintf('Review job for %s tier routed to default queue (priority disabled)', $tierLabel)
            );
        }

        $queue = $context->isEnterpriseTier()
            ? Queue::ReviewsEnterprise
            : Queue::ReviewsPaid;

        $tierLabel = $context->tier ?? 'free';

        return QueueRuleResult::force(
            $queue,
            sprintf('Review job for %s tier routed to %s', $tierLabel, $queue->value)
        );
    }

    /**
     * Get the rule name.
     */
    public function name(): string
    {
        return 'review_job_tier';
    }
}
