<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ValueObjects\ImpactedFile;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves repository coordinates and fetches content for ranked impacted file candidates.
 */
final readonly class FetchImpactedFileBatch
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private ImpactedFileRepositoryCoordinatesResolver $coordinatesResolver = new ImpactedFileRepositoryCoordinatesResolver,
        private ?FetchImpactedFileContent $contentFetcher = null,
    ) {}

    /**
     * Fetch file contents for ranked candidates and return impacted files.
     *
     * @param  array<int, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>  $candidates
     * @return array<int, ImpactedFile>
     */
    public function fetch(Repository $repository, array $candidates, Run $run): array
    {
        $coordinates = $this->coordinatesResolver->resolve($repository, $run);

        if ($coordinates === null) {
            return [];
        }

        $maxFileSize = $this->maxFileSize();
        $contentFetcher = $this->contentFetcher();
        $impactedFiles = [];

        foreach ($candidates as $candidate) {
            try {
                $content = $contentFetcher->fetch(
                    installationId: $coordinates['installation_id'],
                    owner: $coordinates['owner'],
                    repo: $coordinates['repo'],
                    path: $candidate['file_path'],
                    ref: $coordinates['head_sha'],
                    maxFileSize: $maxFileSize,
                );

                if ($content === null) {
                    continue;
                }

                $impactedFiles[] = new ImpactedFile(
                    filePath: $candidate['file_path'],
                    content: $content,
                    matchedSymbol: $candidate['symbol'],
                    matchType: $candidate['match_type'],
                    score: $candidate['score'],
                    matchCount: $candidate['match_count'],
                );
            } catch (Throwable $throwable) {
                Log::debug('ImpactAnalysisCollector: Failed to fetch file', [
                    'file' => $candidate['file_path'],
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return $impactedFiles;
    }

    /**
     * Resolve the maximum file size allowed for impacted file content.
     */
    private function maxFileSize(): int
    {
        return (int) config('reviews.impact_analysis.max_file_size', 50000);
    }

    /**
     * Resolve the impacted-file content fetcher dependency.
     */
    private function contentFetcher(): FetchImpactedFileContent
    {
        return $this->contentFetcher ?? new FetchImpactedFileContent($this->gitHubApiService);
    }
}
