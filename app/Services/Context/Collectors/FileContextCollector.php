<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\FileSelectionPolicy;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\GitHubContentDecoder;
use App\Services\GitHub\Support\RepositoryCoordinatesResolver;
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
     * Maximum file size in bytes (skip large files).
     */
    private const int MAX_FILE_SIZE = 50000;

    /**
     * Create a new FileContextCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private FileSelectionPolicy $selectionPolicy = new FileSelectionPolicy,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private GitHubContentDecoder $contentDecoder = new GitHubContentDecoder,
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

        if (! $coordinates instanceof \App\Services\GitHub\ValueObjects\RepositoryCoordinates) {
            return;
        }

        $headSha = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        if ($headSha === null) {
            Log::debug('FileContextCollector: No head SHA available', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $filesToFetch = $this->selectionPolicy->select($bag->files);

        if ($filesToFetch === []) {
            Log::debug('FileContextCollector: No suitable files to fetch', [
                'repository_id' => $repository->id,
                'total_files' => count($bag->files),
            ]);

            return;
        }

        $fileContents = [];
        $fetchedCount = 0;

        foreach ($filesToFetch as $file) {
            $filename = $file['filename'];

            try {
                $content = $this->fetchFileContent(
                    $coordinates->installationId,
                    $coordinates->owner,
                    $coordinates->repo,
                    $filename,
                    $headSha
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
        ]);
    }

    /**
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        string $ref
    ): ?string {
        $response = $this->gitHubApiService->getFileContents(
            $installationId,
            $owner,
            $repo,
            $path,
            $ref
        );

        if (is_string($response)) {
            return mb_strlen($response) <= self::MAX_FILE_SIZE ? $response : null;
        }

        $size = $response['size'] ?? 0;
        if (! is_int($size) || $size > self::MAX_FILE_SIZE) {
            return null;
        }

        $content = $this->contentDecoder->decode($response);

        if ($content === null) {
            return null;
        }

        return mb_strlen($content) <= self::MAX_FILE_SIZE ? $content : null;
    }
}
