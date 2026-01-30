<?php

declare(strict_types=1);

namespace App\Services\Queue\Rules;

use App\Enums\Queue\Queue;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueRuleResult;

/**
 * Forces system jobs to the system queue.
 *
 * This rule has the highest priority to ensure system-critical jobs
 * are always routed to the isolated system queue.
 */
final readonly class SystemJobRule implements QueueRule
{
    /**
     * Get the rule priority.
     */
    public function priority(): int
    {
        return 1;
    }

    /**
     * Check if this rule applies to the given context.
     */
    public function applies(JobContext $context): bool
    {
        return $context->isSystemJob;
    }

    /**
     * Evaluate the rule and return a result.
     */
    public function evaluate(JobContext $context): QueueRuleResult
    {
        return QueueRuleResult::force(
            Queue::System,
            'System job routed to system queue'
        );
    }

    /**
     * Get the rule name.
     */
    public function name(): string
    {
        return 'system_job';
    }
}
