<?php

declare(strict_types=1);

namespace App\Services\Queue\Contracts;

use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueRuleResult;

/**
 * Contract for queue selection rules.
 *
 * Rules are evaluated in priority order by the QueueResolver.
 * Each rule can force a queue, boost/penalize queue scores, or skip.
 */
interface QueueRule
{
    /**
     * Get the rule's priority.
     *
     * Lower values = evaluated first.
     * Use 0-50 for early rules (like system job detection).
     * Use 50-100 for tier-based rules.
     * Use 100+ for fallback/cleanup rules.
     */
    public function priority(): int;

    /**
     * Check if this rule applies to the given context.
     */
    public function applies(JobContext $context): bool;

    /**
     * Evaluate the rule and return a result.
     *
     * This method is only called if applies() returns true.
     */
    public function evaluate(JobContext $context): QueueRuleResult;

    /**
     * Get a unique identifier for this rule.
     */
    public function name(): string;
}
