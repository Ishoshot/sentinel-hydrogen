<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Models\Repository;
use App\Models\Run;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Context\ValueObjects\ImpactedFile;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

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
        private ?FetchImpactedFileBatch $batchFetcher = null,
    ) {}

    /**
     * @param  array<int, array{name: string, type: string, file: string}>  $symbols
     * @param  array<int, string>  $excludeFiles
     * @return array<int, ImpactedFile>
     */
    public function findImpactedFiles(Repository $repository, array $symbols, array $excludeFiles, Run $run): array
    {
        $searchPatternFactory = $this->searchPatternFactory();
        $candidateCollector = $this->candidateCollector();
        $batchFetcher = $this->batchFetcher();
        $candidateFiles = [];
        $minRelevanceScore = $this->minRelevanceScore();

        foreach ($symbols as $symbol) {
            $searchPatterns = $searchPatternFactory->build($symbol);

            foreach ($searchPatterns as $pattern => $matchType) {
                $results = $this->codeSearchService->keywordSearch(
                    $repository,
                    $pattern,
                    $this->searchLimitPerSymbol()
                );

                $candidateFiles = $candidateCollector->collect(
                    candidates: $candidateFiles,
                    results: $results,
                    symbol: $symbol,
                    excludeFiles: $excludeFiles,
                    matchType: $matchType,
                    minRelevanceScore: $minRelevanceScore,
                );
            }
        }

        $candidateFiles = $candidateCollector->rank($candidateFiles, $this->maxFiles());

        return $batchFetcher->fetch($repository, $candidateFiles, $run);
    }

    /**
     * Resolve the maximum number of impacted files to include.
     */
    private function maxFiles(): int
    {
        return (int) config('reviews.impact_analysis.max_files', 20);
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

    /**
     * Resolve the search pattern factory dependency.
     */
    private function searchPatternFactory(): ImpactSearchPatternFactory
    {
        return $this->searchPatternFactory ?? new ImpactSearchPatternFactory;
    }

    /**
     * Resolve the impacted-file candidate collector dependency.
     */
    private function candidateCollector(): ImpactedFileCandidateCollector
    {
        return $this->candidateCollector ?? new ImpactedFileCandidateCollector;
    }

    /**
     * Resolve the batch fetcher dependency.
     */
    private function batchFetcher(): FetchImpactedFileBatch
    {
        return $this->batchFetcher ?? new FetchImpactedFileBatch($this->gitHubApiService);
    }
}
