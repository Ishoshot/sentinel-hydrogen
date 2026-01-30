<?php

declare(strict_types=1);

namespace App\Services\Logging\ValueObjects;

/**
 * Log context for webhook processing operations.
 */
final readonly class WebhookLogContext
{
    /**
     * Create a new WebhookLogContext instance.
     */
    public function __construct(
        public ?int $githubInstallationId = null,
        public ?string $repositoryName = null,
        public ?string $action = null,
    ) {}

    /**
     * Create from optional values.
     */
    public static function create(
        ?int $installationId = null,
        ?string $repositoryName = null,
        ?string $action = null
    ): self {
        return new self(
            githubInstallationId: $installationId,
            repositoryName: $repositoryName,
            action: $action,
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
            'github_installation_id' => $this->githubInstallationId,
            'repository_name' => $this->repositoryName,
            'action' => $this->action,
        ], fn (int|string|null $v): bool => $v !== null);
    }
}
