<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\Strategies\QueueRuleEvaluationStrategy;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueResolution;

/**
 * Central service for determining which queue a job should be dispatched to.
 *
 * The resolver applies rules in priority order and produces a deterministic
 * result based on the job context and configured rules.
 */
final class QueueResolver
{
    /**
     * Registered queue selection rules.
     *
     * @var array<int, QueueRule>
     */
    private array $rules = [];

    /**
     * Applies queue rules and returns accumulated scoring context.
     */
    private readonly QueueRuleEvaluationStrategy $ruleEvaluationRunner;

    /**
     * @param  iterable<QueueRule>  $rules
     */
    public function __construct(
        iterable $rules = [],
        /** Queue scoring strategy for tie-breaking and default selection. */
        private readonly QueueScorer $scorer = new QueueScorer(),
        /** Logger used to emit queue resolution traces. */
        private readonly ResolutionLogger $logger = new ResolutionLogger(),
        ?QueueRuleEvaluationStrategy $ruleEvaluationRunner = null,
    ) {
        $this->ruleEvaluationRunner = $ruleEvaluationRunner ?? new QueueRuleEvaluationStrategy($this->scorer);

        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * Add a rule to the resolver.
     */
    public function addRule(QueueRule $rule): self
    {
        $this->rules[] = $rule;
        $this->sortRules();

        return $this;
    }

    /**
     * Enable debug mode for detailed logging.
     */
    public function enableDebugMode(): self
    {
        $this->logger->enableDebugMode();

        return $this;
    }

    /**
     * Resolve the appropriate queue for the given context.
     */
    public function resolve(JobContext $context): QueueResolution
    {
        $evaluation = $this->ruleEvaluationRunner->evaluate($this->rules, $context);

        if ($evaluation->wasForced() && $evaluation->forcedQueue instanceof \App\Enums\Queue\Queue && $evaluation->forcedBy !== null) {
            return $this->logger->buildResolution(
                queue: $evaluation->forcedQueue,
                trace: $evaluation->trace,
                context: $context,
                forcedBy: $evaluation->forcedBy,
                reason: $evaluation->forcedReason ?? 'Selected by queue rule',
            );
        }

        $scores = $evaluation->scores;
        if ($scores === []) {
            $scores = $this->scorer->initializeScores();
        }

        $selectedQueue = $this->scorer->selectByScore($scores);

        return $this->logger->buildResolution(
            queue: $selectedQueue,
            trace: $evaluation->trace,
            context: $context,
            forcedBy: null,
            reason: 'Selected by highest score',
            scores: $scores,
        );
    }

    /**
     * Resolve and return just the queue name as a string.
     *
     * Convenience method for when you just need the queue name.
     */
    public function resolveQueueName(JobContext $context): string
    {
        return $this->resolve($context)->queue->value;
    }

    /**
     * Get all registered rules.
     *
     * @return array<int, QueueRule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Sort rules by priority (lowest first).
     */
    private function sortRules(): void
    {
        usort($this->rules, fn (QueueRule $a, QueueRule $b): int => $a->priority() <=> $b->priority());
    }
}
