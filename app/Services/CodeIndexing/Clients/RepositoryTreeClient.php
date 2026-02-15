<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Clients;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches the repository file tree from GitHub.
 */
final readonly class RepositoryTreeClient
{
    /**
     * Create a new RepositoryTreeClient instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
    ) {}

    /**
     * Get the repository tree from GitHub.
     *
     * @return array<int, array{path: string, type: string, size?: int}>
     */
    public function resolve(int $installationId, string $owner, string $repo, string $sha): array
    {
        try {
            $result = $this->githubApi->getRepositoryTree($installationId, $owner, $repo, $sha, true);

            /** @var array<int, array{path: string, type: string, size?: int}> $tree */
            $tree = $result['tree'];

            return $tree;
        } catch (Throwable $throwable) {
            Log::error('Failed to get repository tree', [
                'owner' => $owner,
                'repo' => $repo,
                'sha' => $sha,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }
}
