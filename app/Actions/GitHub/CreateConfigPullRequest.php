<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Events\GitHub\ConfigPullRequestCreated;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\ValueObjects\ConfigPullRequestResult;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

final readonly class CreateConfigPullRequest
{
    private const string BRANCH_NAME = 'sentinel/add-config';

    private const string PR_TITLE = 'feat(sentinel): add sentinel configuration file';

    private const string CONFIG_PATH = '.sentinel/config.yaml';

    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * Prepare a branch with the Sentinel configuration file and return a compare URL.
     *
     * This creates the branch and config file, but lets the user create the PR
     * themselves via the GitHub compare page.
     */
    public function handle(Repository $repository): ConfigPullRequestResult
    {
        $repository->loadMissing('installation');

        $installation = $repository->installation;

        if ($installation === null) {
            return ConfigPullRequestResult::failed('Repository has no associated installation');
        }

        $owner = $repository->owner;
        $repo = $repository->name;
        $installationId = $installation->installation_id;
        $defaultBranch = $repository->default_branch ?? 'main';

        Log::info('Preparing config branch for repository', [
            'repository_id' => $repository->id,
            'full_name' => $repository->full_name,
        ]);

        try {
            // 1. Check if config already exists
            if ($this->configExists($installationId, $owner, $repo, $defaultBranch)) {
                Log::info('Config file already exists', ['repository' => $repository->full_name]);

                return ConfigPullRequestResult::skipped('Configuration file already exists');
            }

            // 2. Check if branch already exists - return compare URL so user can continue
            if ($this->branchExists($installationId, $owner, $repo)) {
                Log::info('Config branch already exists, returning compare URL', ['repository' => $repository->full_name]);

                $compareUrl = $this->buildCompareUrl($owner, $repo, $defaultBranch);

                return ConfigPullRequestResult::ready($compareUrl);
            }

            // 3. Get the SHA of the default branch
            $defaultBranchSha = $this->getDefaultBranchSha($installationId, $owner, $repo, $defaultBranch);

            // 4. Create the branch
            $this->createBranch($installationId, $owner, $repo, $defaultBranchSha);

            // 5. Create the config file on the new branch
            $this->createConfigFile($installationId, $owner, $repo);

            // 6. Build the compare URL for the user to create the PR
            $compareUrl = $this->buildCompareUrl($owner, $repo, $defaultBranch);

            Log::info('Config branch ready for PR', [
                'repository' => $repository->full_name,
                'compare_url' => $compareUrl,
            ]);

            // 7. Broadcast the event for WebSocket notification
            ConfigPullRequestCreated::dispatch(
                $repository->workspace_id,
                $repository->id,
                $repository->full_name,
                $compareUrl
            );

            return ConfigPullRequestResult::ready($compareUrl);
        } catch (RuntimeException $runtimeException) {
            $message = $runtimeException->getMessage();

            // Handle specific GitHub API errors
            if (str_contains($message, '403') || str_contains(mb_strtolower($message), 'permission') || str_contains(mb_strtolower($message), 'resource not accessible')) {
                Log::warning('Insufficient permissions to create config branch', [
                    'repository' => $repository->full_name,
                    'error' => $message,
                ]);

                return ConfigPullRequestResult::failed('Insufficient permissions. Please check your GitHub App permissions.');
            }

            Log::error('Failed to create config branch', [
                'repository' => $repository->full_name,
                'error' => $message,
            ]);

            return ConfigPullRequestResult::failed($message);
        }
    }

    /**
     * Build the GitHub compare URL for creating a PR.
     */
    private function buildCompareUrl(string $owner, string $repo, string $baseBranch): string
    {
        return sprintf(
            'https://github.com/%s/%s/compare/%s...%s?expand=1',
            $owner,
            $repo,
            $baseBranch,
            self::BRANCH_NAME
        );
    }

    /**
     * Check if the config file already exists on the default branch.
     */
    private function configExists(int $installationId, string $owner, string $repo, string $ref): bool
    {
        return $this->gitHubApiService->fileExists($installationId, $owner, $repo, self::CONFIG_PATH, $ref);
    }

    /**
     * Check if the config branch already exists.
     */
    private function branchExists(int $installationId, string $owner, string $repo): bool
    {
        try {
            $this->gitHubApiService->getReference($installationId, $owner, $repo, 'heads/'.self::BRANCH_NAME);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get the SHA of the default branch.
     */
    private function getDefaultBranchSha(int $installationId, string $owner, string $repo, string $branch): string
    {
        $reference = $this->gitHubApiService->getReference($installationId, $owner, $repo, 'heads/'.$branch);

        /** @var array{sha: string} $object */
        $object = $reference['object'];

        return $object['sha'];
    }

    /**
     * Create the config branch from the default branch.
     */
    private function createBranch(int $installationId, string $owner, string $repo, string $sha): void
    {
        $this->gitHubApiService->createReference(
            $installationId,
            $owner,
            $repo,
            'refs/heads/'.self::BRANCH_NAME,
            $sha
        );
    }

    /**
     * Create the config file on the new branch.
     */
    private function createConfigFile(int $installationId, string $owner, string $repo): void
    {
        $this->gitHubApiService->createFile(
            $installationId,
            $owner,
            $repo,
            self::CONFIG_PATH,
            $this->getDefaultConfigContent(),
            self::PR_TITLE,
            self::BRANCH_NAME
        );
    }

    /**
     * Get the default config file content.
     *
     * Reads from the canonical example config file to ensure consistency.
     */
    private function getDefaultConfigContent(): string
    {
        $exampleConfigPath = base_path('.sentinel/config.example.yaml');
        $content = file_get_contents($exampleConfigPath);

        if ($content === false) {
            throw new \RuntimeException('Failed to read example config file');
        }

        return $content;
    }
}
