<?php

declare(strict_types=1);

namespace App\Actions\CodeIndexing;

use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\CodeIndexing\Strategies\IndexBatchDispatchStrategy;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class HandlePullRequestPreIndex
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private CodeIndexingServiceContract $codeIndexingService,
        private IndexBatchDispatchStrategy $indexBatchDispatchStrategy,
    ) {}

    /**
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    public function handle(Repository $repository, array $payload): void
    {
        if (! $this->isEligibleTier($repository)) {
            return;
        }

        $installation = $repository->installation;
        if (! $installation instanceof \App\Models\Installation) {
            Log::warning('Pull request pre-index skipped: installation missing', [
                'repository_id' => $repository->id,
                'pr_number' => $payload['pull_request_number'],
            ]);

            return;
        }

        $pullRequestNumber = $payload['pull_request_number'];
        $headSha = $payload['head_sha'];

        $lockKey = sprintf('pr_preindex:%d:%d:%s', $repository->id, $pullRequestNumber, $headSha);
        $lock = Cache::lock($lockKey, 180);

        if (! $lock->get()) {
            Log::debug('Pull request pre-index already running', [
                'repository_id' => $repository->id,
                'pr_number' => $pullRequestNumber,
                'head_sha' => $headSha,
            ]);

            return;
        }

        try {
            $files = $this->gitHubApiService->getPullRequestFiles(
                $installation->installation_id,
                $repository->owner,
                $repository->name,
                $pullRequestNumber,
            );

            if ($this->exceedsEligibilityThresholds($files)) {
                Log::info('Pull request pre-index skipped by size thresholds', [
                    'repository_id' => $repository->id,
                    'pr_number' => $pullRequestNumber,
                    'files_changed' => count($files),
                    'lines_changed' => $this->totalChangedLines($files),
                ]);

                return;
            }

            $maxFileSize = (int) config('reviews.pr_preindex.indexing.max_file_size', 120000);
            $maxFiles = max(1, (int) config('reviews.pr_preindex.indexing.max_files', 40));
            $batchSize = max(1, (int) config('reviews.pr_preindex.indexing.batch_size', 50));

            $indexableFiles = $this->resolveIndexableFiles($files, $maxFileSize, $maxFiles);

            if ($indexableFiles === []) {
                Log::debug('Pull request pre-index skipped: no indexable files', [
                    'repository_id' => $repository->id,
                    'pr_number' => $pullRequestNumber,
                    'head_sha' => $headSha,
                ]);

                return;
            }

            $scope = CodeIndexScope::pullRequest($pullRequestNumber, $headSha);

            $deleted = CodeIndex::query()
                ->forRepository($repository)
                ->forPullRequest($pullRequestNumber)
                ->delete();

            $this->indexBatchDispatchStrategy->dispatch($repository, $headSha, $indexableFiles, $scope, $batchSize);

            Log::info('Pull request pre-index dispatched', [
                'repository_id' => $repository->id,
                'pr_number' => $pullRequestNumber,
                'head_sha' => $headSha,
                'indexable_files' => count($indexableFiles),
                'removed_previous_scope_rows' => $deleted,
                'max_files' => $maxFiles,
                'max_file_size' => $maxFileSize,
                'batch_size' => $batchSize,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function exceedsEligibilityThresholds(array $files): bool
    {
        $maxFilesChanged = max(1, (int) config('reviews.pr_preindex.eligibility.max_files_changed', 120));
        $maxLinesChanged = max(1, (int) config('reviews.pr_preindex.eligibility.max_lines_changed', 8000));

        return count($files) > $maxFilesChanged || $this->totalChangedLines($files) > $maxLinesChanged;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array{path: string, type: string, size?: int}>
     */
    private function resolveIndexableFiles(array $files, int $maxFileSize, int $maxFiles): array
    {
        $resolved = [];

        foreach ($files as $file) {
            $path = is_string($file['filename'] ?? null) ? $file['filename'] : null;
            if (! is_string($path)) {
                continue;
            }

            if ($path === '') {
                continue;
            }

            $status = is_string($file['status'] ?? null) ? $file['status'] : 'modified';
            if ($status === 'removed') {
                continue;
            }

            $size = is_int($file['size'] ?? null) ? $file['size'] : null;
            if ($size !== null && $size > $maxFileSize) {
                continue;
            }

            if (! $this->codeIndexingService->shouldIndexFile($path)) {
                continue;
            }

            $resolved[$path] = ['path' => $path, 'type' => 'blob'];

            if ($size !== null) {
                $resolved[$path]['size'] = $size;
            }

            if (count($resolved) >= $maxFiles) {
                break;
            }
        }

        return array_values($resolved);
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function totalChangedLines(array $files): int
    {
        return array_sum(array_map(
            static fn (array $file): int => max(
                (int) ($file['changes'] ?? 0),
                (int) ($file['additions'] ?? 0) + (int) ($file['deletions'] ?? 0),
            ),
            $files
        ));
    }

    /**
     * Determine whether PR pre-indexing is enabled for the repository tier.
     */
    private function isEligibleTier(Repository $repository): bool
    {
        $tiers = config('reviews.pr_preindex.eligibility.tiers', ['illuminate', 'orchestrate', 'sanctum']);

        if (! is_array($tiers) || $tiers === []) {
            return false;
        }

        $tier = $repository->workspace?->getCurrentTier() ?? 'foundation';

        return in_array($tier, $tiers, true);
    }
}
