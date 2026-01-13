<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Models\Installation;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Throwable;

/**
 * Helper class for building consistent log context arrays.
 *
 * Use this to ensure all logs have searchable/traceable identifiers.
 */
final class LogContext
{
    /**
     * Build context from a Run model.
     *
     * @return array{run_id: int, workspace_id: int|null, repository_id: int|null}
     */
    public static function fromRun(Run $run): array
    {
        return [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'repository_id' => $run->repository_id,
        ];
    }

    /**
     * Build context from a Repository model.
     *
     * @return array{repository_id: int, workspace_id: int|null, installation_id: int|null, repository_name: string|null}
     */
    public static function fromRepository(Repository $repository): array
    {
        return [
            'repository_id' => $repository->id,
            'workspace_id' => $repository->workspace_id,
            'installation_id' => $repository->installation_id,
            'repository_name' => $repository->full_name,
        ];
    }

    /**
     * Build context from a Workspace model.
     *
     * @return array{workspace_id: int, workspace_name: string|null}
     */
    public static function fromWorkspace(Workspace $workspace): array
    {
        return [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
        ];
    }

    /**
     * Build context from an Installation model.
     *
     * @return array{installation_id: int, github_installation_id: int, workspace_id: int|null}
     */
    public static function fromInstallation(Installation $installation): array
    {
        return [
            'installation_id' => $installation->id,
            'github_installation_id' => $installation->installation_id,
            'workspace_id' => $installation->workspace_id,
        ];
    }

    /**
     * Build context for webhook processing.
     *
     * @return array<string, int|string>
     */
    public static function forWebhook(?int $installationId = null, ?string $repositoryName = null, ?string $action = null): array
    {
        return array_filter([
            'github_installation_id' => $installationId,
            'repository_name' => $repositoryName,
            'action' => $action,
        ], static fn (int|string|null $v): bool => $v !== null);
    }

    /**
     * Build context for PR-related operations.
     *
     * @return array<string, mixed>
     */
    public static function forPullRequest(
        ?int $repositoryId = null,
        ?int $prNumber = null,
        ?string $repositoryName = null,
        ?int $workspaceId = null
    ): array {
        return array_filter([
            'repository_id' => $repositoryId,
            'workspace_id' => $workspaceId,
            'pr_number' => $prNumber,
            'repository_name' => $repositoryName,
        ], static fn (int|string|null $v): bool => $v !== null);
    }

    /**
     * Merge multiple context arrays.
     *
     * @param  array<string, mixed>  ...$contexts
     * @return array<string, mixed>
     */
    public static function merge(array ...$contexts): array
    {
        return array_merge(...$contexts);
    }

    /**
     * Add exception context to an existing context array.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function withException(array $context, Throwable $exception): array
    {
        return array_merge($context, [
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
        ]);
    }
}
