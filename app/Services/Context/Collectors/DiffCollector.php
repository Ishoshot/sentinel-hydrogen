<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\GitHubApiService;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
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
        private GitHubApiService $gitHubApiService,
        private FetchesSentinelConfig $fetchConfig,
        private SentinelConfigParser $configParser,
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
        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            Log::warning('DiffCollector: Repository has no installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            Log::warning('DiffCollector: Invalid repository full name', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;
        $pullRequestNumber = $metadata->int('pull_request_number');

        if ($pullRequestNumber <= 0) {
            Log::warning('DiffCollector: Invalid PR number', ['run_id' => $run->id]);

            return;
        }

        $bag->pullRequest = $this->extractPullRequestData($metadata, $fullName);

        $files = $this->gitHubApiService->getPullRequestFiles(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber
        );

        // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
        if (! is_array($files)) {
            Log::warning('DiffCollector: Unexpected response format from GitHub API', [
                'pr_number' => $pullRequestNumber,
            ]);

            return;
        }

        $bag->files = $this->normalizeFiles($files);
        $bag->metrics = $this->calculateMetrics($bag->files);

        // Fetch sentinel config with fallback: base_branch -> default_branch
        $baseBranch = $bag->pullRequest['base_branch'];
        $defaultBranch = $repository->default_branch;

        $configResult = $this->fetchConfigWithFallback($repository, $baseBranch, $defaultBranch);

        if ($configResult['config'] !== null) {
            $bag->metadata['sentinel_config'] = $configResult['config'];
            $bag->metadata['paths_config'] = $configResult['config']['paths'] ?? [];
        }

        $bag->metadata['config_from_branch'] = $configResult['branch'];

        Log::info('DiffCollector: Collected PR data', [
            'repository' => $fullName,
            'pr_number' => $pullRequestNumber,
            'files_count' => count($bag->files),
            'files_with_patches' => $bag->getFilesWithPatchCount(),
            'config_from_branch' => $configResult['branch'],
        ]);
    }

    /**
     * Fetch sentinel config with fallback: base_branch -> default_branch.
     *
     * @return array{config: array<string, mixed>|null, branch: string|null}
     */
    private function fetchConfigWithFallback(
        Repository $repository,
        ?string $baseBranch,
        ?string $defaultBranch
    ): array {
        // Build ordered list of branches to try (deduplicated)
        $branches = array_values(array_unique(array_filter([
            $baseBranch,
            $defaultBranch,
        ])));

        foreach ($branches as $branch) {
            $config = $this->fetchAndParseConfig($repository, $branch);

            if ($config !== null) {
                Log::debug('DiffCollector: Found sentinel config', [
                    'repository' => $repository->full_name,
                    'branch' => $branch,
                    'tried_branches' => $branches,
                ]);

                return ['config' => $config, 'branch' => $branch];
            }
        }

        Log::debug('DiffCollector: No sentinel config found in any branch', [
            'repository' => $repository->full_name,
            'tried_branches' => $branches,
        ]);

        return ['config' => null, 'branch' => null];
    }

    /**
     * Fetch and parse sentinel config from a specific branch.
     *
     * @return array<string, mixed>|null
     */
    private function fetchAndParseConfig(Repository $repository, string $branch): ?array
    {
        $fetchResult = $this->fetchConfig->handle($repository, $branch);

        if (! $fetchResult['found'] || $fetchResult['content'] === null) {
            return null;
        }

        $parseResult = $this->configParser->tryParse($fetchResult['content']);

        if (! $parseResult['success'] || $parseResult['config'] === null) {
            Log::warning('DiffCollector: Failed to parse sentinel config', [
                'repository' => $repository->full_name,
                'branch' => $branch,
                'error' => $parseResult['error'] ?? 'Unknown error',
            ]);

            return null;
        }

        return $parseResult['config']->toArray();
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

    /**
     * Normalize GitHub file data to include patches.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function normalizeFiles(array $files): array
    {
        return array_map(function (array $file): array {
            $extractor = MetadataExtractor::from($file);

            return [
                'filename' => $extractor->string('filename'),
                'status' => $extractor->string('status', 'modified'),
                'additions' => $extractor->int('additions'),
                'deletions' => $extractor->int('deletions'),
                'changes' => $extractor->int('changes'),
                'patch' => $extractor->stringOrNull('patch'),
            ];
        }, $files);
    }

    /**
     * Calculate metrics from normalized files.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{files_changed: int, lines_added: int, lines_deleted: int}
     */
    private function calculateMetrics(array $files): array
    {
        return [
            'files_changed' => count($files),
            'lines_added' => array_sum(array_column($files, 'additions')),
            'lines_deleted' => array_sum(array_column($files, 'deletions')),
        ];
    }
}
