<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\FileSelectionPolicy;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Policies\AdaptiveReviewLimitPolicy;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Resolvers\RepositoryCoordinatesResolver;
use App\Services\GitHub\ValueObjects\RepositoryCoordinates;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects full file contents for touched files in the PR.
 *
 * Fetches the complete file content (not just the diff) to provide
 * the AI with surrounding context for better understanding of changes.
 */
final readonly class FileContextCollector implements ContextCollector
{
    /**
     * Create a new FileContextCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private AdaptiveReviewLimitPolicy $limitPolicy = new AdaptiveReviewLimitPolicy,
        private FileSelectionPolicy $selectionPolicy = new FileSelectionPolicy,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private ?FetchFileContent $fileContentFetcher = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'file_context';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 85;
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

        $metadata = $run->metadata ?? [];
        $coordinates = $this->coordinatesResolver->resolve($repository);

        if (! $coordinates instanceof RepositoryCoordinates) {
            return;
        }

        $headSha = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        if ($headSha === null) {
            Log::debug('FileContextCollector: No head SHA available', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $limits = $this->limitPolicy->fileContextLimits($repository, $bag->files);
        $filesToFetch = $this->selectionPolicy->select($bag->files, $limits['max_files']);

        if ($filesToFetch === []) {
            Log::debug('FileContextCollector: No suitable files to fetch', [
                'repository_id' => $repository->id,
                'total_files' => count($bag->files),
                'limit_max_files' => $limits['max_files'],
                'limit_tier' => $limits['tier'],
                'limit_pr_size_bucket' => $limits['pr_size_bucket'],
                'limit_source' => $limits['source'],
            ]);

            return;
        }

        $fetcher = $this->fileContentFetcher ?? new FetchFileContent($this->gitHubApiService);

        $fileContents = [];
        $fetchedCount = 0;

        foreach ($filesToFetch as $file) {
            $filename = $file['filename'];

            try {
                $content = $fetcher->fetch(
                    $coordinates->installationId,
                    $coordinates->owner,
                    $coordinates->repo,
                    $filename,
                    $headSha,
                    $limits['max_file_size'],
                );

                if ($content !== null) {
                    $fileContents[$filename] = $content;
                    $fetchedCount++;
                }
            } catch (Throwable $e) {
                Log::debug('FileContextCollector: Failed to fetch file', [
                    'file' => $filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bag->fileContents = $fileContents;

        Log::info('FileContextCollector: Collected file contents', [
            'repository' => $coordinates->fullName,
            'files_fetched' => $fetchedCount,
            'files_requested' => count($filesToFetch),
            'limit_max_files' => $limits['max_files'],
            'limit_max_file_size' => $limits['max_file_size'],
            'limit_tier' => $limits['tier'],
            'limit_pr_size_bucket' => $limits['pr_size_bucket'],
            'limit_source' => $limits['source'],
            'adaptive_limits_enabled' => $limits['adaptive'],
        ]);
    }
}
