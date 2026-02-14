<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Enums\Queue\Queue;
use App\Services\Queue\ValueObjects\JobContext;
use App\Services\Queue\ValueObjects\QueueResolution;
use Illuminate\Support\Facades\Log;

/**
 * Builds QueueResolution objects and handles optional debug logging.
 */
final class ResolutionLogger
{
    private bool $debugMode = false;

    /**
     * Enable debug mode for detailed logging.
     */
    public function enableDebugMode(): void
    {
        $this->debugMode = true;
    }

    /**
     * Build the final resolution object and optionally emit a debug log.
     *
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<string, int>|null  $scores
     */
    public function buildResolution(
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
