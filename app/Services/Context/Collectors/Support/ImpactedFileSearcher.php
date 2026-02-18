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
     * @param  array{max_symbols: int, max_files: int, max_file_size: int, search_limit_per_symbol: int, min_relevance_score: float, tier: string, pr_size_bucket: string, source: string, adaptive: bool}  $limits
     * @return array<int, ImpactedFile>
     */
    public function findImpactedFiles(Repository $repository, array $symbols, array $excludeFiles, Run $run, array $limits): array
    {
        $searchPatternFactory = $this->searchPatternFactory();
        $candidateCollector = $this->candidateCollector();
        $batchFetcher = $this->batchFetcher();
        $candidateFiles = [];
        $searchLimitPerSymbol = max(1, (int) $limits['search_limit_per_symbol']);
        $maxFiles = max(1, (int) $limits['max_files']);
        $maxFileSize = max(1, (int) $limits['max_file_size']);
        $minRelevanceScore = min(1.0, max(0.0, (float) $limits['min_relevance_score']));

        foreach ($symbols as $symbol) {
            $searchPatterns = $searchPatternFactory->build($symbol);

            foreach ($searchPatterns as $pattern => $matchType) {
                $results = $this->codeSearchService->keywordSearch(
                    $repository,
                    $pattern,
                    $searchLimitPerSymbol
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

        $candidateFiles = $candidateCollector->rank($candidateFiles, $maxFiles);

        return $batchFetcher->fetch($repository, $candidateFiles, $run, $maxFileSize);
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
