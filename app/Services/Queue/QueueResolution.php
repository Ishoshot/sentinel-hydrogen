<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Enums\Queue;

/**
 * The result of queue resolution.
 *
 * Contains the selected queue, reasoning, and full trace of rule evaluation
 * for observability and debugging.
 */
final readonly class QueueResolution
{
    /**
     * @param  Queue  $queue  The resolved queue
     * @param  string|null  $forcedBy  Name of rule that forced this queue, or null if scored
     * @param  string  $reason  Human-readable reason for selection
     * @param  array<int, array<string, mixed>>  $trace  Full trace of rule evaluations
     * @param  array<string, int>|null  $scores  Final scores for each queue (if not forced)
     */
    public function __construct(
        public Queue $queue,
        public ?string $forcedBy,
        public string $reason,
        public array $trace = [],
        public ?array $scores = null,
    ) {}

    /**
     * Check if this resolution was forced by a rule.
     */
    public function wasForced(): bool
    {
        return $this->forcedBy !== null;
    }

    /**
     * Get the queue name as a string.
     */
    public function queueName(): string
    {
        return $this->queue->value;
    }

    /**
     * Convert to array for logging/debugging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue->value,
            'forced_by' => $this->forcedBy,
            'reason' => $this->reason,
            'trace' => $this->trace,
            'scores' => $this->scores,
        ];
    }
}
