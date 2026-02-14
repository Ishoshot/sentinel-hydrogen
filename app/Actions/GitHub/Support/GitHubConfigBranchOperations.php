<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Github\Exception\RuntimeException;

final readonly class GitHubConfigBranchOperations
{
    /**
     * Create a new operations instance.
     */
    public function __construct(private GitHubApiServiceContract $gitHubApiService) {}

    /**
     * Determine whether the Sentinel config file already exists on the target ref.
     */
    public function configExists(int $installationId, string $owner, string $repo, string $configPath, string $ref): bool
    {
        return $this->gitHubApiService->fileExists($installationId, $owner, $repo, $configPath, $ref);
    }

    /**
     * Determine whether the target configuration branch already exists.
     */
    public function branchExists(int $installationId, string $owner, string $repo, string $branchName): bool
    {
        try {
            $this->gitHubApiService->getReference($installationId, $owner, $repo, 'heads/'.$branchName);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Fetch the SHA for the repository default branch.
     */
    public function getDefaultBranchSha(int $installationId, string $owner, string $repo, string $branch): string
    {
        $reference = $this->gitHubApiService->getReference($installationId, $owner, $repo, 'heads/'.$branch);

        /** @var array{sha: string} $object */
        $object = $reference['object'];

        return $object['sha'];
    }

    /**
     * Create the target branch used for the config pull request.
     */
    public function createBranch(int $installationId, string $owner, string $repo, string $branchName, string $sha): void
    {
        $this->gitHubApiService->createReference(
            $installationId,
            $owner,
            $repo,
            'refs/heads/'.$branchName,
            $sha
        );
    }

    /**
     * Create the Sentinel configuration file in the target branch.
     */
    public function createConfigFile(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        string $content,
        string $message,
        string $branchName
    ): void {
        $this->gitHubApiService->createFile(
            $installationId,
            $owner,
            $repo,
            $path,
            $content,
            $message,
            $branchName
        );
    }
}
