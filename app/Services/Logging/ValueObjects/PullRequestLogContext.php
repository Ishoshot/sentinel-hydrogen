<?php

declare(strict_types=1);

namespace App\Services\Logging\ValueObjects;

/**
 * Log context for PR-related operations.
 */
final readonly class PullRequestLogContext
{
    /**
     * Create a new PullRequestLogContext instance.
     */
    public function __construct(
        public ?int $repositoryId = null,
        public ?int $prNumber = null,
        public ?string $repositoryName = null,
        public ?int $workspaceId = null,
    ) {}

    /**
     * Create from optional values.
     */
    public static function create(
        ?int $repositoryId = null,
        ?int $prNumber = null,
        ?string $repositoryName = null,
        ?int $workspaceId = null
    ): self {
        return new self(
            repositoryId: $repositoryId,
            prNumber: $prNumber,
            repositoryName: $repositoryName,
            workspaceId: $workspaceId,
        );
    }

    /**
     * Convert to array for logging (filters out null values).
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'repository_id' => $this->repositoryId,
            'workspace_id' => $this->workspaceId,
            'pr_number' => $this->prNumber,
            'repository_name' => $this->repositoryName,
        ], fn (int|string|null $v): bool => $v !== null);
    }
}
