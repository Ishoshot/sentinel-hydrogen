<?php

declare(strict_types=1);

namespace App\Services\Logging\ValueObjects;

use App\Models\Workspace;

/**
 * Log context for Workspace-related operations.
 */
final readonly class WorkspaceLogContext
{
    /**
     * Create a new WorkspaceLogContext instance.
     */
    public function __construct(
        public int $workspaceId,
        public ?string $workspaceName = null,
    ) {}

    /**
     * Create from a Workspace model.
     */
    public static function fromWorkspace(Workspace $workspace): self
    {
        return new self(
            workspaceId: $workspace->id,
            workspaceName: $workspace->name,
        );
    }

    /**
     * Convert to array for logging.
     *
     * @return array{workspace_id: int, workspace_name: string|null}
     */
    public function toArray(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'workspace_name' => $this->workspaceName,
        ];
    }
}
