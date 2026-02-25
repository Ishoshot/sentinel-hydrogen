<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Actions\SentinelConfig\Handlers\SentinelConfigFetchExceptionHandler;
use App\Actions\SentinelConfig\Resolvers\SentinelConfigFetchTargetResolver;
use App\Actions\SentinelConfig\Resolvers\SentinelConfigGitHubResponseResolver;
use App\Actions\SentinelConfig\ValueObjects\ConfigFetchResult;
use App\Actions\SentinelConfig\ValueObjects\SentinelConfigFetchTarget;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Github\Exception\RuntimeException;

/**
 * Fetches .sentinel/config.yaml from a repository via the GitHub API.
 */
final readonly class FetchSentinelConfig implements FetchesSentinelConfig
{
    private const string CONFIG_PATH = '.sentinel/config.yaml';

    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $github,
        private SentinelConfigFetchTargetResolver $targetResolver,
        private SentinelConfigGitHubResponseResolver $responseResolver,
        private SentinelConfigFetchExceptionHandler $exceptionHandler,
    ) {}

    /**
     * Fetch .sentinel/config.yaml from a repository.
     *
     * @param  Repository  $repository  The repository to fetch the config from
     * @param  string|null  $ref  The branch/ref to fetch from (defaults to repository's default branch)
     */
    public function handle(Repository $repository, ?string $ref = null): ConfigFetchResult
    {
        $resolution = $this->targetResolver->resolve($repository, $ref);

        if ($resolution->failureResult instanceof ConfigFetchResult) {
            return $resolution->failureResult;
        }

        /** @var SentinelConfigFetchTarget $target */
        $target = $resolution->target;

        try {
            $response = $this->github->getFileContents(
                $target->installationId,
                $target->owner,
                $target->repo,
                self::CONFIG_PATH,
                $target->branch
            );

            return $this->responseResolver->parse($response);
        } catch (RuntimeException $runtimeException) {
            return $this->exceptionHandler->handle($repository, $runtimeException);
        }
    }
}
