<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Builders\GitHubConfigCompareUrlBuilder;
use App\Actions\GitHub\Support\GitHubConfigBranchOperations;
use App\Actions\GitHub\Support\GitHubDefaultConfigContentReader;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\ValueObjects\ConfigPullRequestResult;
use Github\Exception\RuntimeException;

final readonly class PrepareConfigBranch
{
    private const string BRANCH_NAME = 'sentinel/add-config';

    private const string PR_TITLE = 'feat(sentinel): add sentinel configuration file';

    private const string CONFIG_PATH = '.sentinel/config.yaml';

    /**
     * Branch operations for Sentinel config bootstrap.
     */
    private GitHubConfigBranchOperations $operations;

    /**
     * Create a new action instance.
     */
    public function __construct(
        GitHubApiServiceContract $gitHubApiService,
        ?GitHubConfigBranchOperations $operations = null,
        /** Builds GitHub compare URLs for follow-up pull request links. */
        private GitHubConfigCompareUrlBuilder $compareUrlBuilder = new GitHubConfigCompareUrlBuilder,
        /** Loads the default Sentinel config file content. */
        private GitHubDefaultConfigContentReader $defaultConfigContentReader = new GitHubDefaultConfigContentReader,
    ) {
        $this->operations = $operations ?? new GitHubConfigBranchOperations($gitHubApiService);
    }

    /**
     * @return array{result: ConfigPullRequestResult, should_dispatch_event: bool}
     *
     * @throws RuntimeException
     */
    public function handle(int $installationId, string $owner, string $repo, string $defaultBranch): array
    {
        if ($this->operations->configExists($installationId, $owner, $repo, self::CONFIG_PATH, $defaultBranch)) {
            return [
                'result' => ConfigPullRequestResult::skipped('Configuration file already exists'),
                'should_dispatch_event' => false,
            ];
        }

        if ($this->operations->branchExists($installationId, $owner, $repo, self::BRANCH_NAME)) {
            return [
                'result' => ConfigPullRequestResult::ready($this->compareUrlBuilder->build($owner, $repo, $defaultBranch, self::BRANCH_NAME)),
                'should_dispatch_event' => false,
            ];
        }

        $defaultBranchSha = $this->operations->getDefaultBranchSha($installationId, $owner, $repo, $defaultBranch);
        $this->operations->createBranch($installationId, $owner, $repo, self::BRANCH_NAME, $defaultBranchSha);
        $this->operations->createConfigFile(
            $installationId,
            $owner,
            $repo,
            self::CONFIG_PATH,
            $this->defaultConfigContentReader->read(),
            self::PR_TITLE,
            self::BRANCH_NAME,
        );

        return [
            'result' => ConfigPullRequestResult::ready($this->compareUrlBuilder->build($owner, $repo, $defaultBranch, self::BRANCH_NAME)),
            'should_dispatch_event' => true,
        ];
    }
}
