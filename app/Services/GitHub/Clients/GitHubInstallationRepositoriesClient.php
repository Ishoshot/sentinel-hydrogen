<?php

declare(strict_types=1);

namespace App\Services\GitHub\Clients;

use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubInstallationRepositoriesClient
{
    /**
     * Create a new paginator instance.
     */
    public function __construct(private GitHubApiRequestClient $requestExecutor) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(int $installationId): array
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
