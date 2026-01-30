<?php

declare(strict_types=1);

namespace App\Services\Queue\ValueObjects;

use App\Enums\Queue\Queue;

/**
 * Result of a queue rule evaluation.
 *
 * Rules can:
 * - Force a specific queue (queue resolution stops)
 * - Boost a queue's score (make it more likely to be selected)
 * - Penalize a queue's score (make it less likely to be selected)
 * - Skip (no effect on the decision)
 */
final readonly class QueueRuleResult
{
    /**
     * Create a new result instance.
     */
    private function __construct(
        public bool $shouldForce,
        public ?Queue $forcedQueue,
        public int $scoreAdjustment,
        public ?Queue $targetQueue,
        public string $reason,
    ) {}

    /**
     * Force a specific queue to be used.
     *
     * When a rule forces a queue, the resolver immediately returns
     * that queue without evaluating further rules.
     */
    public static function force(Queue $queue, string $reason = ''): self
    {
        return new self(
            shouldForce: true,
            forcedQueue: $queue,
            scoreAdjustment: 0,
            targetQueue: null,
            reason: $reason,
        );
    }

    /**
     * Boost a queue's score (make it more likely to be selected).
     *
     * Higher boost values = more likely to be selected.
     * Typical values: 10-50 for minor boost, 50-100 for significant boost.
     */
    public static function boost(Queue $queue, int $amount, string $reason = ''): self
    {
        return new self(
            shouldForce: false,
            forcedQueue: null,
            scoreAdjustment: abs($amount),
            targetQueue: $queue,
            reason: $reason,
        );
    }

    /**
     * Penalize a queue's score (make it less likely to be selected).
     *
     * Higher penalty values = less likely to be selected.
     * Typical values: 10-50 for minor penalty, 50-100 for significant penalty.
     */
    public static function penalize(Queue $queue, int $amount, string $reason = ''): self
    {
        return new self(
            shouldForce: false,
            forcedQueue: null,
            scoreAdjustment: -abs($amount),
            targetQueue: $queue,
            reason: $reason,
        );
    }

    /**
     * Skip - this rule has no effect on queue selection.
     */
    public static function skip(string $reason = ''): self
    {
        return new self(
            shouldForce: false,
            forcedQueue: null,
            scoreAdjustment: 0,
            targetQueue: null,
            reason: $reason,
        );
    }

    /**
     * Check if this result forces a queue.
     */
    public function isForced(): bool
    {
        return $this->shouldForce && $this->forcedQueue instanceof Queue;
    }

    /**
     * Check if this result has any effect.
     */
    public function hasEffect(): bool
    {
        return $this->shouldForce || ($this->scoreAdjustment !== 0 && $this->targetQueue instanceof Queue);
    }

    /**
     * Convert to array for debugging/logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'should_force' => $this->shouldForce,
            'forced_queue' => $this->forcedQueue?->value,
            'score_adjustment' => $this->scoreAdjustment,
            'target_queue' => $this->targetQueue?->value,
            'reason' => $this->reason,
        ];
    }
}
