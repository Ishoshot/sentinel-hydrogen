<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Enums\Queue\Queue;
use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueResolution;
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
     * Whether debug logging is enabled.
     */
    private bool $debugMode = false;

    /**
     * @param  iterable<QueueRule>  $rules
     */
    public function __construct(iterable $rules = [])
    {
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
        $trace = [];
        $scores = $this->initializeScores();

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

            // If a rule forces a queue, return immediately
            if ($result->isForced() && $result->forcedQueue !== null) {
                return $this->buildResolution(
                    queue: $result->forcedQueue,
                    trace: $trace,
                    context: $context,
                    forcedBy: $rule->name(),
                    reason: $result->reason,
                );
            }

            // Apply score adjustments
            if ($result->hasEffect() && $result->targetQueue !== null) {
                $scores[$result->targetQueue->value] += $result->scoreAdjustment;
            }
        }

        // No rule forced a queue, select the highest-scoring queue
        $selectedQueue = $this->selectByScore($scores);

        return $this->buildResolution(
            queue: $selectedQueue,
            trace: $trace,
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
     * Initialize all queue scores based on their base priority.
     *
     * Higher base priority (lower number) = higher initial score.
     * We invert the priority so that queues with lower priority numbers
     * start with higher scores.
     *
     * @return non-empty-array<string, int>
     */
    private function initializeScores(): array
    {
        $scores = [];
        $maxPriority = 100;

        foreach (Queue::cases() as $queue) {
            // Invert priority: lower priority number = higher score
            $scores[$queue->value] = $maxPriority - $queue->priority();
        }

        return $scores;
    }

    /**
     * Select the queue with the highest score.
     *
     * @param  non-empty-array<string, int>  $scores
     */
    private function selectByScore(array $scores): Queue
    {
        $maxScore = max($scores);
        $candidates = array_keys(array_filter($scores, fn (int $score): bool => $score === $maxScore));

        // If there are ties, select the one with the lowest base priority
        $selectedValue = $candidates[0];
        $selectedPriority = Queue::from($selectedValue)->priority();

        foreach ($candidates as $candidate) {
            $candidatePriority = Queue::from($candidate)->priority();
            if ($candidatePriority < $selectedPriority) {
                $selectedValue = $candidate;
                $selectedPriority = $candidatePriority;
            }
        }

        return Queue::from($selectedValue);
    }

    /**
     * Build the final resolution object.
     *
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

    /**
     * Sort rules by priority (lowest first).
     */
    private function sortRules(): void
    {
        usort($this->rules, fn (QueueRule $a, QueueRule $b): int => $a->priority() <=> $b->priority());
    }
}
