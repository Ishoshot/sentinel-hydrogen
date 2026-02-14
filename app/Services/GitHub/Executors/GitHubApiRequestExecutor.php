<?php

declare(strict_types=1);

namespace App\Services\GitHub\Executors;

use App\Models\Installation;
use App\Services\GitHub\Support\GitHubApiOperationRunner;
use Closure;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubApiRequestExecutor
{
    /**
     * Create a new request executor instance.
     */
    public function __construct(private GitHubApiOperationRunner $operationRunner) {}

    /**
     * @template TResult
     *
     * @param  Closure(GitHubManager): TResult  $operation
     * @return TResult
     */
    public function runApp(string $operationName, Closure $operation): mixed
    {
        return $this->operationRunner->runWithAppAuthentication($operationName, $operation);
    }

    /**
     * @template TResult
     *
     * @param  Closure(GitHubManager): TResult  $operation
     * @return TResult
     */
    public function runInstallation(int $installationId, string $operationName, Closure $operation): mixed
    {
        return $this->operationRunner->runWithInstallationAuthentication($installationId, $operationName, $operation);
    }

    /**
     * Get an authenticated GitHub client for an installation.
     */
    public function getClientForInstallation(Installation $installation): GitHubManager
    {
        return $this->operationRunner->authenticateInstallation($installation->installation_id);
    }
}
