<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Models\Repository;
use App\Models\Run;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Context\ValueObjects\ImpactedFile;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Searches indexed code for symbol references and resolves impacted file contents.
 */
final readonly class ImpactedFileSearcher
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private CodeSearchServiceContract $codeSearchService,
        private GitHubApiServiceContract $gitHubApiService,
        private ?ImpactSearchPatternFactory $searchPatternFactory = null,
        private ?ImpactedFileCandidateCollector $candidateCollector = null,
        private ?ImpactedFileRepositoryCoordinatesResolver $coordinatesResolver = null,
        private ?ImpactedFileContentFetcher $contentFetcher = null,
    ) {}

    /**
     * @param  array<int, array{name: string, type: string, file: string}>  $symbols
     * @param  array<int, string>  $excludeFiles
     * @return array<int, ImpactedFile>
     */
    public function findImpactedFiles(Repository $repository, array $symbols, array $excludeFiles, Run $run): array
    {
        $candidateFiles = [];
        $minRelevanceScore = $this->minRelevanceScore();

        foreach ($symbols as $symbol) {
            $searchPatterns = $this->searchPatternFactory()->build($symbol);

            foreach ($searchPatterns as $pattern => $matchType) {
                $results = $this->codeSearchService->keywordSearch(
                    $repository,
                    $pattern,
                    $this->searchLimitPerSymbol()
                );

                $candidateFiles = $this->candidateCollector()->collect(
                    candidates: $candidateFiles,
                    results: $results,
                    symbol: $symbol,
                    excludeFiles: $excludeFiles,
                    matchType: $matchType,
                    minRelevanceScore: $minRelevanceScore,
                );
            }
        }

        $candidateFiles = $this->candidateCollector()->rank($candidateFiles, $this->maxFiles());

        return $this->fetchFileContents($repository, $candidateFiles, $run);
    }

    /**
     * @param  array<int, array{file_path: string, symbol: string, match_type: string, score: float, match_count: int, content: string}>  $candidates
     * @return array<int, ImpactedFile>
     */
    private function fetchFileContents(Repository $repository, array $candidates, Run $run): array
    {
        $coordinates = $this->coordinatesResolver()->resolve($repository, $run);

        if ($coordinates === null) {
            return [];
        }

        $impactedFiles = [];

        foreach ($candidates as $candidate) {
            try {
                $content = $this->contentFetcher()->fetch(
                    installationId: $coordinates['installation_id'],
                    owner: $coordinates['owner'],
                    repo: $coordinates['repo'],
                    path: $candidate['file_path'],
                    ref: $coordinates['head_sha'],
                    maxFileSize: $this->maxFileSize(),
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
     * Resolve the maximum number of impacted files to include.
     */
    private function maxFiles(): int
    {
        return (int) config('reviews.impact_analysis.max_files', 20);
    }

    /**
     * Resolve the maximum file size allowed for impacted file content.
     */
    private function maxFileSize(): int
    {
        return (int) config('reviews.impact_analysis.max_file_size', 50000);
    }

    /**
     * Resolve the per-symbol search result cap.
     */
    private function searchLimitPerSymbol(): int
    {
        return (int) config('reviews.impact_analysis.search_limit_per_symbol', 50);
    }

    /**
     * Resolve the minimum score required to keep a search result.
     */
    private function minRelevanceScore(): float
    {
        $value = config('reviews.impact_analysis.min_relevance_score', 0.3);

        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.3;
    }

    private function searchPatternFactory(): ImpactSearchPatternFactory
    {
        return $this->searchPatternFactory ?? new ImpactSearchPatternFactory;
    }

    private function candidateCollector(): ImpactedFileCandidateCollector
    {
        return $this->candidateCollector ?? new ImpactedFileCandidateCollector;
    }

    private function coordinatesResolver(): ImpactedFileRepositoryCoordinatesResolver
    {
        return $this->coordinatesResolver ?? new ImpactedFileRepositoryCoordinatesResolver;
    }

    private function contentFetcher(): ImpactedFileContentFetcher
    {
        return $this->contentFetcher ?? new ImpactedFileContentFetcher($this->gitHubApiService);
    }
}
