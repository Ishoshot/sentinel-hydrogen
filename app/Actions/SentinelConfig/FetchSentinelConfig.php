<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Actions\SentinelConfig\Handlers\SentinelConfigFetchExceptionHandler;
use App\Actions\SentinelConfig\Parsers\SentinelConfigGitHubResponseParser;
use App\Actions\SentinelConfig\Resolvers\SentinelConfigFetchTargetResolver;
use App\Actions\SentinelConfig\Support\SentinelConfigFetchTarget;
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
        private SentinelConfigGitHubResponseParser $responseParser,
        private SentinelConfigFetchExceptionHandler $exceptionHandler,
    ) {}

    /**
     * Fetch .sentinel/config.yaml from a repository.
     *
     * @param  Repository  $repository  The repository to fetch the config from
     * @param  string|null  $ref  The branch/ref to fetch from (defaults to repository's default branch)
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function handle(Repository $repository, ?string $ref = null): array
    {
        $resolution = $this->targetResolver->resolve($repository, $ref);

        if ($resolution->failureResult !== null) {
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

            return $this->responseParser->parse($response);
        } catch (RuntimeException $runtimeException) {
            return $this->exceptionHandler->handle($repository, $runtimeException);
        }
    }
}
