<?php

declare(strict_types=1);

namespace App\Services\Logging\ValueObjects;

use App\Models\Installation;

/**
 * Log context for Installation-related operations.
 */
final readonly class InstallationLogContext
{
    /**
     * Create a new InstallationLogContext instance.
     */
    public function __construct(
        public int $installationId,
        public int $githubInstallationId,
        public ?int $workspaceId = null,
    ) {}

    /**
     * Create from an Installation model.
     */
    public static function fromInstallation(Installation $installation): self
    {
        return new self(
            installationId: $installation->id,
            githubInstallationId: $installation->installation_id,
            workspaceId: $installation->workspace_id,
        );
    }

    /**
     * Convert to array for logging.
     *
     * @return array{installation_id: int, github_installation_id: int, workspace_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'installation_id' => $this->installationId,
            'github_installation_id' => $this->githubInstallationId,
            'workspace_id' => $this->workspaceId,
        ];
    }
}
