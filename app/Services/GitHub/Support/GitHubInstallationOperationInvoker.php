<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use App\Services\GitHub\Executors\GitHubApiRequestExecutor;
use Closure;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubInstallationOperationInvoker
{
    /**
     * Create a new installation operation invoker instance.
     */
    public function __construct(
        private GitHubApiRequestExecutor $requestExecutor,
        private GitHubApiResponseGuard $responseGuard,
    ) {}

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array<string, mixed>
     */
    public function map(int $installationId, string $operationName, Closure $operation): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            $operationName,
            $operation,
        ));
    }

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array<string, mixed>|string
     */
    public function mapOrString(int $installationId, string $operationName, Closure $operation): array|string
    {
        return $this->responseGuard->mapOrString($this->requestExecutor->runInstallation(
            $installationId,
            $operationName,
            $operation,
        ));
    }

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array<int, array<string, mixed>>
     */
    public function listOfMaps(int $installationId, string $operationName, Closure $operation): array
    {
        return $this->responseGuard->listOfMaps($this->requestExecutor->runInstallation(
            $installationId,
            $operationName,
            $operation,
        ));
    }

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array{sha: string, url: string, tree: array<int, array{path: string, mode: string, type: string, sha: string, size?: int}>, truncated: bool}
     */
    public function repositoryTree(int $installationId, string $operationName, Closure $operation): array
    {
        return $this->responseGuard->repositoryTree($this->requestExecutor->runInstallation(
            $installationId,
            $operationName,
            $operation,
        ));
    }
}
