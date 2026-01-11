<?php

declare(strict_types=1);

namespace App\Services\Queue\Rules;

use App\Enums\Queue;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\JobContext;
use App\Services\Queue\QueueRuleResult;

/**
 * Forces webhook jobs to the webhooks queue.
 *
 * Webhooks need fast processing to acknowledge external systems
 * and should be isolated from other workloads.
 */
final readonly class WebhookJobRule implements QueueRule
{
    /**
     * Get the rule priority.
     */
    public function priority(): int
    {
        return 5;
    }

    /**
     * Check if this rule applies to the given context.
     */
    public function applies(JobContext $context): bool
    {
        return $context->getMeta('source') === 'webhook';
    }

    /**
     * Evaluate the rule and return a result.
     */
    public function evaluate(JobContext $context): QueueRuleResult
    {
        return QueueRuleResult::force(
            Queue::Webhooks,
            'Webhook job routed to webhooks queue'
        );
    }

    /**
     * Get the rule name.
     */
    public function name(): string
    {
        return 'webhook_job';
    }
}
