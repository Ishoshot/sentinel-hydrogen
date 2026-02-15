<?php

declare(strict_types=1);

namespace App\Services\Queue\ValueObjects;

use App\Enums\Queue\Queue;

final readonly class QueueRuleEvaluationOutcome
{
    /**
     * Create a new evaluation outcome instance.
     *
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<string, int>  $scores
     */
    public function __construct(
        public ?Queue $forcedQueue,
        public ?string $forcedBy,
        public ?string $forcedReason,
        public array $trace,
        public array $scores,
    ) {}

    /**
     * Determine if evaluation resolved to a forced queue.
     */
    public function wasForced(): bool
    {
        return $this->forcedQueue instanceof Queue
            && is_string($this->forcedBy)
            && $this->forcedBy !== '';
    }
}
