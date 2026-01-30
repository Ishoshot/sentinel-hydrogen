<?php

declare(strict_types=1);

namespace App\Services\Logging\ValueObjects;

use App\Models\Repository;

/**
 * Log context for Repository-related operations.
 */
final readonly class RepositoryLogContext
{
    /**
     * Create a new RepositoryLogContext instance.
     */
    public function __construct(
        public int $repositoryId,
        public ?int $workspaceId = null,
        public ?int $installationId = null,
        public ?string $repositoryName = null,
    ) {}

    /**
     * Create from a Repository model.
     */
    public static function fromRepository(Repository $repository): self
    {
        return new self(
            repositoryId: $repository->id,
            workspaceId: $repository->workspace_id,
            installationId: $repository->installation_id,
            repositoryName: $repository->full_name,
        );
    }

    /**
     * Convert to array for logging.
     *
     * @return array{repository_id: int, workspace_id: int|null, installation_id: int|null, repository_name: string|null}
     */
    public function toArray(): array
    {
        return [
            'repository_id' => $this->repositoryId,
            'workspace_id' => $this->workspaceId,
            'installation_id' => $this->installationId,
            'repository_name' => $this->repositoryName,
        ];
    }
}
