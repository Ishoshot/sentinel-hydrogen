<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\DiffFileNormalizer;
use App\Services\Context\Collectors\Support\DiffPullRequestDataExtractor;
use App\Services\Context\Collectors\Support\SentinelConfigBranchFetcher;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\RepositoryCoordinatesResolver;
use App\Support\MetadataExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Collects PR metadata and file diffs from GitHub.
 *
 * This is the highest priority collector as code changes are essential for reviews.
 */
final readonly class DiffCollector implements ContextCollector
{
    /**
     * Create a new collector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private SentinelConfigBranchFetcher $configFetcher,
        private DiffFileNormalizer $fileNormalizer = new DiffFileNormalizer,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private DiffPullRequestDataExtractor $prDataExtractor = new DiffPullRequestDataExtractor,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'diff';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 100;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        $metadata = MetadataExtractor::from($run->metadata ?? []);
        $coordinates = $this->coordinatesResolver->resolve($repository);

        if (! $coordinates instanceof \App\Services\GitHub\ValueObjects\RepositoryCoordinates) {
            Log::warning('DiffCollector: Repository has no installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $pullRequestNumber = $metadata->int('pull_request_number');

        if ($pullRequestNumber <= 0) {
            Log::warning('DiffCollector: Invalid PR number', ['run_id' => $run->id]);

            return;
        }

        $bag->pullRequest = $this->prDataExtractor->extract($metadata, $coordinates->fullName);

        $files = $this->gitHubApiService->getPullRequestFiles(
            $coordinates->installationId,
            $coordinates->owner,
            $coordinates->repo,
            $pullRequestNumber
        );

        // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
        if (! is_array($files)) {
            Log::warning('DiffCollector: Unexpected response format from GitHub API', [
                'pr_number' => $pullRequestNumber,
            ]);

            return;
        }

        $bag->files = $this->fileNormalizer->normalize($files);
        $bag->metrics = $this->fileNormalizer->calculateMetrics($bag->files);

        // Fetch sentinel config with fallback: base_branch -> default_branch
        $baseBranch = $bag->pullRequest['base_branch'];
        $defaultBranch = $repository->default_branch;

        $configResult = $this->configFetcher->fetch($repository, $baseBranch, $defaultBranch);

        if ($configResult['config'] !== null) {
            $bag->metadata['sentinel_config'] = $configResult['config'];
            $bag->metadata['paths_config'] = $configResult['config']['paths'] ?? [];
        }

        $bag->metadata['config_from_branch'] = $configResult['branch'];

        Log::info('DiffCollector: Collected PR data', [
            'repository' => $coordinates->fullName,
            'pr_number' => $pullRequestNumber,
            'files_count' => count($bag->files),
            'files_with_patches' => $bag->getFilesWithPatchCount(),
            'config_from_branch' => $configResult['branch'],
        ]);
    }
}
