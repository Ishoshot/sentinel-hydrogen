<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\DiffFileNormalizer;
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

        $bag->pullRequest = $this->extractPullRequestData($metadata, $coordinates->fullName);

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

    /**
     * Extract PR metadata from run metadata.
     *
     * @return array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    private function extractPullRequestData(MetadataExtractor $metadata, string $fullName): array
    {
        return [
            'number' => $metadata->int('pull_request_number'),
            'title' => $metadata->string('pull_request_title'),
            'body' => $metadata->stringOrNull('pull_request_body'),
            'base_branch' => $metadata->string('base_branch', 'main'),
            'head_branch' => $metadata->string('head_branch'),
            'head_sha' => $metadata->string('head_sha'),
            'sender_login' => $metadata->string('sender_login'),
            'repository_full_name' => $fullName,
            'author' => $metadata->author(),
            'is_draft' => $metadata->bool('is_draft'),
            'assignees' => $metadata->users('assignees'),
            'reviewers' => $metadata->users('reviewers'),
            'labels' => $metadata->labels(),
        ];
    }
}
