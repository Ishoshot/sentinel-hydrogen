<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\ValueObjects\ConfigPullRequestResult;
use Github\Exception\RuntimeException;
use RuntimeException as LocalRuntimeException;

final readonly class PrepareConfigBranch
{
    private const string BRANCH_NAME = 'sentinel/add-config';

    private const string PR_TITLE = 'feat(sentinel): add sentinel configuration file';

    private const string CONFIG_PATH = '.sentinel/config.yaml';

    /**
     * Create a new action instance.
     */
    public function __construct(private GitHubApiServiceContract $gitHubApiService) {}

    /**
     * @return array{result: ConfigPullRequestResult, should_dispatch_event: bool}
     *
     * @throws RuntimeException
     */
    public function handle(int $installationId, string $owner, string $repo, string $defaultBranch): array
    {
        if ($this->configExists($installationId, $owner, $repo, $defaultBranch)) {
            return [
                'result' => ConfigPullRequestResult::skipped('Configuration file already exists'),
                'should_dispatch_event' => false,
            ];
        }

        if ($this->branchExists($installationId, $owner, $repo)) {
            return [
                'result' => ConfigPullRequestResult::ready($this->buildCompareUrl($owner, $repo, $defaultBranch)),
                'should_dispatch_event' => false,
            ];
        }

        $defaultBranchSha = $this->getDefaultBranchSha($installationId, $owner, $repo, $defaultBranch);
        $this->createBranch($installationId, $owner, $repo, $defaultBranchSha);
        $this->createConfigFile($installationId, $owner, $repo);

        return [
            'result' => ConfigPullRequestResult::ready($this->buildCompareUrl($owner, $repo, $defaultBranch)),
            'should_dispatch_event' => true,
        ];
    }

    /**
     * Build the compare URL for the prepared branch.
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
     * Determine whether the Sentinel config file already exists on the target ref.
     */
    private function configExists(int $installationId, string $owner, string $repo, string $ref): bool
    {
        return $this->gitHubApiService->fileExists($installationId, $owner, $repo, self::CONFIG_PATH, $ref);
    }

    /**
     * Determine whether the target configuration branch already exists.
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
     * Fetch the SHA for the repository default branch.
     */
    private function getDefaultBranchSha(int $installationId, string $owner, string $repo, string $branch): string
    {
        $reference = $this->gitHubApiService->getReference($installationId, $owner, $repo, 'heads/'.$branch);

        /** @var array{sha: string} $object */
        $object = $reference['object'];

        return $object['sha'];
    }

    /**
     * Create the target branch used for the config pull request.
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
     * Create the default Sentinel configuration file in the target branch.
     */
    private function createConfigFile(int $installationId, string $owner, string $repo): void
    {
        $this->gitHubApiService->createFile(
            $installationId,
            $owner,
            $repo,
            self::CONFIG_PATH,
            $this->defaultConfigContent(),
            self::PR_TITLE,
            self::BRANCH_NAME
        );
    }

    /**
     * Load the default Sentinel configuration content.
     */
    private function defaultConfigContent(): string
    {
        $exampleConfigPath = base_path('.sentinel/config.example.yaml');
        $content = file_get_contents($exampleConfigPath);

        if ($content === false) {
            throw new LocalRuntimeException('Failed to read example config file');
        }

        return $content;
    }
}
