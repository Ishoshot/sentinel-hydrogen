<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\CodeIndex;
use App\Models\Repository;
use App\Models\Run;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Context\Collectors\Support\ImpactedFileSearcher;
use App\Services\Context\Collectors\Support\ImpactModifiedSymbolExtractor;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Policies\AdaptiveReviewLimitPolicy;
use App\Services\Context\ValueObjects\ImpactedFile;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;

/**
 * Collects files that reference symbols modified in the current PR.
 */
final readonly class ImpactAnalysisCollector implements ContextCollector
{
    /**
     * Create a new ImpactAnalysisCollector instance.
     */
    public function __construct(
        private CodeSearchServiceContract $codeSearchService,
        private GitHubApiServiceContract $gitHubApiService,
        private AdaptiveReviewLimitPolicy $limitPolicy = new AdaptiveReviewLimitPolicy,
        private ?ImpactModifiedSymbolExtractor $symbolExtractor = null,
        private ?ImpactedFileSearcher $fileSearcher = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'impact_analysis';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 75;
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
        $symbolExtractor = $this->symbolExtractor();
        $fileSearcher = $this->fileSearcher();

        if (! $this->hasCodeIndex($repository)) {
            Log::debug('ImpactAnalysisCollector: Repository has no code index', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        if ($bag->semantics === []) {
            Log::debug('ImpactAnalysisCollector: No semantic data available');

            return;
        }

        $modifiedSymbols = $symbolExtractor->extractModifiedSymbols($bag);

        if ($modifiedSymbols === []) {
            Log::debug('ImpactAnalysisCollector: No modified symbols found');

            return;
        }

        $impactLimits = $this->limitPolicy->impactAnalysisLimits($repository, $bag->files);
        $symbolsToSearch = array_slice($modifiedSymbols, 0, $impactLimits['max_symbols']);

        Log::debug('ImpactAnalysisCollector: Searching for references', [
            'symbols_count' => count($symbolsToSearch),
            'total_modified' => count($modifiedSymbols),
            'limit_max_symbols' => $impactLimits['max_symbols'],
            'limit_max_files' => $impactLimits['max_files'],
            'limit_max_file_size' => $impactLimits['max_file_size'],
            'limit_search_limit_per_symbol' => $impactLimits['search_limit_per_symbol'],
            'limit_min_relevance_score' => $impactLimits['min_relevance_score'],
            'limit_tier' => $impactLimits['tier'],
            'limit_pr_size_bucket' => $impactLimits['pr_size_bucket'],
            'limit_source' => $impactLimits['source'],
            'adaptive_limits_enabled' => $impactLimits['adaptive'],
        ]);

        $prFiles = array_column($bag->files, 'filename');

        $impactedFiles = $fileSearcher->findImpactedFiles($repository, $symbolsToSearch, $prFiles, $run, $impactLimits);

        if ($impactedFiles === []) {
            Log::debug('ImpactAnalysisCollector: No impacted files found');

            return;
        }

        $bag->impactedFiles = array_map(
            fn (ImpactedFile $file): array => $file->toArray(),
            $impactedFiles
        );

        Log::info('ImpactAnalysisCollector: Found impacted files', [
            'repository' => $repository->full_name,
            'symbols_searched' => count($symbolsToSearch),
            'impacted_files' => count($impactedFiles),
            'limit_max_symbols' => $impactLimits['max_symbols'],
            'limit_max_files' => $impactLimits['max_files'],
            'limit_max_file_size' => $impactLimits['max_file_size'],
            'limit_search_limit_per_symbol' => $impactLimits['search_limit_per_symbol'],
            'limit_min_relevance_score' => $impactLimits['min_relevance_score'],
            'limit_tier' => $impactLimits['tier'],
            'limit_pr_size_bucket' => $impactLimits['pr_size_bucket'],
            'limit_source' => $impactLimits['source'],
            'adaptive_limits_enabled' => $impactLimits['adaptive'],
        ]);
    }

    /**
     * Determine whether the repository has indexed code available.
     */
    private function hasCodeIndex(Repository $repository): bool
    {
        return CodeIndex::forRepository($repository)->exists();
    }

    /**
     * Resolve the symbol extractor dependency.
     */
    private function symbolExtractor(): ImpactModifiedSymbolExtractor
    {
        return $this->symbolExtractor ?? new ImpactModifiedSymbolExtractor;
    }

    /**
     * Resolve the file searcher dependency.
     */
    private function fileSearcher(): ImpactedFileSearcher
    {
        return $this->fileSearcher ?? new ImpactedFileSearcher($this->codeSearchService, $this->gitHubApiService);
    }
}
