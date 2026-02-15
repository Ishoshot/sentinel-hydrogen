<?php

declare(strict_types=1);

namespace App\Services\Queue\Strategies;

use App\Services\Queue\Contracts\QueueRule;
use App\Services\Queue\QueueScorer;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueRuleEvaluationOutcome;

final readonly class QueueRuleEvaluationStrategy
{
    /**
     * Create a new rule evaluation runner instance.
     */
    public function __construct(private QueueScorer $scorer) {}

    /**
     * Evaluate queue rules against the current job context.
     *
     * @param  array<int, QueueRule>  $rules
     */
    public function evaluate(array $rules, JobContext $context): QueueRuleEvaluationOutcome
    {
        $trace = [];
        $scores = $this->scorer->initializeScores();

        foreach ($rules as $rule) {
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
}
