<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Enums\Queue\Queue;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueResolution;
use App\Services\Queue\ValueObjects\QueueRuleEvaluationOutcome;
use Illuminate\Support\Facades\Log;

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
     * Indicates whether debug logs should be emitted when resolving queues.
     */
    private bool $debugMode = false;

    /**
     * @param  iterable<QueueRule>  $rules
     */
    public function __construct(
        iterable $rules = [],
        /** Queue scoring strategy for tie-breaking and default selection. */
        private readonly QueueScorer $scorer = new QueueScorer(),
    ) {
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
        $this->debugMode = true;

        return $this;
    }

    /**
     * Resolve the appropriate queue for the given context.
     */
    public function resolve(JobContext $context): QueueResolution
    {
        $evaluation = $this->evaluateRules($context);

        if ($evaluation->wasForced() && $evaluation->forcedQueue instanceof Queue && $evaluation->forcedBy !== null) {
            return $this->buildResolution(
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

        return $this->buildResolution(
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

    private function evaluateRules(JobContext $context): QueueRuleEvaluationOutcome
    {
        $trace = [];
        $scores = $this->scorer->initializeScores();

        foreach ($this->rules as $rule) {
            if (! $rule->applies($context)) {
                $trace[] = [
                    'rule' => $rule->name(),
                    'applied' => false,
                    'reason' => 'Rule does not apply to context',
                ];

                continue;
            }

            $result = $rule->evaluate($context);

            $trace[] = [
                'rule' => $rule->name(),
                'applied' => true,
                'result' => $result->toArray(),
            ];

            if ($result->isForced() && $result->forcedQueue !== null) {
                return new QueueRuleEvaluationOutcome(
                    forcedQueue: $result->forcedQueue,
                    forcedBy: $rule->name(),
                    forcedReason: $result->reason,
                    trace: $trace,
                    scores: $scores,
                );
            }

            if ($result->hasEffect() && $result->targetQueue !== null) {
                $scores[$result->targetQueue->value] += $result->scoreAdjustment;
            }
        }

        return new QueueRuleEvaluationOutcome(
            forcedQueue: null,
            forcedBy: null,
            forcedReason: null,
            trace: $trace,
            scores: $scores,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<string, int>|null  $scores
     */
    private function buildResolution(
        Queue $queue,
        array $trace,
        JobContext $context,
        ?string $forcedBy,
        string $reason,
        ?array $scores = null,
    ): QueueResolution {
        $resolution = new QueueResolution(
            queue: $queue,
            forcedBy: $forcedBy,
            reason: $reason,
            trace: $trace,
            scores: $scores,
        );

        if ($this->debugMode) {
            Log::debug('Queue resolution', [
                'job_class' => $context->jobClass,
                'workspace_id' => $context->workspaceId,
                'tier' => $context->tier,
                'resolved_queue' => $queue->value,
                'forced_by' => $forcedBy,
                'reason' => $reason,
                'trace' => $trace,
            ]);
        }

        return $resolution;
    }
}
