<?php

declare(strict_types=1);

namespace App\Services\GitHub\Clients;

use App\Models\Installation;
use Closure;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubApiRequestClient
{
    /**
     * Create a new request executor instance.
     */
    public function __construct(private GitHubApiOperationClient $operationRunner) {}

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
