<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\Contracts\GitHubRateLimiterContract;
use Closure;
use GrahamCampbell\GitHub\GitHubManager;

/**
 * Executes GitHub API operations with required authentication and rate-limiting.
 */
final readonly class GitHubApiOperationRunner
{
    /**
     * Create a new operation runner instance.
     */
    public function __construct(
        private GitHubManager $github,
        private GitHubAppServiceContract $appService,
        private GitHubRateLimiterContract $rateLimiter,
    ) {}

    /**
     * @template TResult
     *
     * @param  Closure(GitHubManager): TResult  $operation
     * @return TResult
     */
    public function runWithAppAuthentication(string $operationName, Closure $operation): mixed
    {
        $jwt = $this->appService->generateJwt();
        $this->github->connection()->authenticate($jwt, authMethod: 'jwt');

        return $this->rateLimiter->handle(
            fn (): mixed => $operation($this->github),
            $operationName,
        );
    }

    /**
     * @template TResult
     *
     * @param  Closure(GitHubManager): TResult  $operation
     * @return TResult
     */
    public function runWithInstallationAuthentication(int $installationId, string $operationName, Closure $operation): mixed
    {
        $token = $this->appService->getInstallationToken($installationId);
        $this->github->connection()->authenticate($token, authMethod: 'access_token_header');

        return $this->rateLimiter->handle(
            fn (): mixed => $operation($this->github),
            $operationName,
        );
    }

    /**
     * Authenticate with an installation token and return the shared GitHub manager.
     */
    public function authenticateInstallation(int $installationId): GitHubManager
    {
        $token = $this->appService->getInstallationToken($installationId);
        $this->github->connection()->authenticate($token, authMethod: 'access_token_header');

        return $this->github;
    }
}
