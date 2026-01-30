<?php

declare(strict_types=1);

namespace App\Services\Logging\ValueObjects;

use App\Models\Run;

/**
 * Log context for Run-related operations.
 */
final readonly class RunLogContext
{
    /**
     * Create a new RunLogContext instance.
     */
    public function __construct(
        public int $runId,
        public ?int $workspaceId = null,
        public ?int $repositoryId = null,
    ) {}

    /**
     * Create from a Run model.
     */
    public static function fromRun(Run $run): self
    {
        return new self(
            runId: $run->id,
            workspaceId: $run->workspace_id,
            repositoryId: $run->repository_id,
        );
    }

    /**
     * Convert to array for logging.
     *
     * @return array{run_id: int, workspace_id: int|null, repository_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'workspace_id' => $this->workspaceId,
            'repository_id' => $this->repositoryId,
        ];
    }
}
