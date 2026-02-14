<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use App\Services\GitHub\Executors\GitHubApiRequestExecutor;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubInstallationRepositoriesPaginator
{
    /**
     * Create a new paginator instance.
     */
    public function __construct(private GitHubApiRequestExecutor $requestExecutor) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(int $installationId): array
    {
        $repositories = [];
        $page = 1;
        $perPage = 100;

        do {
            /** @var array{repositories?: array<int, array<string, mixed>>} $response */
            $response = $this->requestExecutor->runInstallation(
                $installationId,
                sprintf('listRepositories(installation=%d, page=%d)', $installationId, $page),
                fn (GitHubManager $github): array => $github->connection()->apps()->listRepositories($page),
            );

            /** @var array<int, array<string, mixed>> $repos */
            $repos = $response['repositories'] ?? [];
            $repositories = array_merge($repositories, $repos);
            $page++;
        } while (count($repos) === $perPage);

        return $repositories;
    }
}
