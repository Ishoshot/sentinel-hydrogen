<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Handlers\GitHubConfigBranchHandler;
use App\Actions\GitHub\Resolvers\GitHubConfigCompareUrlResolver;
use App\Actions\GitHub\Resolvers\GitHubDefaultConfigContentResolver;
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
    private GitHubConfigBranchHandler $operations;

    /**
     * Create a new action instance.
     */
    public function __construct(
        GitHubApiServiceContract $gitHubApiService,
        ?GitHubConfigBranchHandler $operations = null,
        /** Builds GitHub compare URLs for follow-up pull request links. */
        private GitHubConfigCompareUrlResolver $compareUrlBuilder = new GitHubConfigCompareUrlResolver,
        /** Loads the default Sentinel config file content. */
        private GitHubDefaultConfigContentResolver $defaultConfigContentReader = new GitHubDefaultConfigContentResolver,
    ) {
        $this->operations = $operations ?? new GitHubConfigBranchHandler($gitHubApiService);
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
