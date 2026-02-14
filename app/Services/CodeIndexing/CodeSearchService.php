<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\CodeIndexing\Support\CodeSearchCacheKeyFactory;
use App\Services\CodeIndexing\Support\HybridSearchResultMerger;
use App\Services\CodeIndexing\Support\KeywordCodeSearchExecutor;
use App\Services\CodeIndexing\Support\SemanticCodeSearchExecutor;
use App\Services\CodeIndexing\Support\SymbolCodeSearchExecutor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for searching indexed code using hybrid search.
 */
final readonly class CodeSearchService implements CodeSearchServiceContract
{
    private const int CACHE_TTL = 900; // 15 minutes

    /**
     * Create a new CodeSearchService instance.
     */
    public function __construct(
        private KeywordCodeSearchExecutor $keywordSearchExecutor,
        private SemanticCodeSearchExecutor $semanticSearchExecutor,
        private SymbolCodeSearchExecutor $symbolSearchExecutor,
        private HybridSearchResultMerger $resultMerger,
        private CodeSearchCacheKeyFactory $cacheKeyFactory,
    ) {}

    /**
     * Search code using hybrid search (keyword + semantic).
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}>
     */
    public function search(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array
    {
        $cacheKey = $this->cacheKeyFactory->build('hybrid', $repository->id, $query, $limit, $fileTypes);

        /** @var array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes): array {
            Log::debug('Performing hybrid search', [
                'repository_id' => $repository->id,
                'query' => $query,
                'limit' => $limit,
            ]);

            $keywordResults = $this->keywordSearchExecutor->execute($repository, $query, $limit * 2, $fileTypes);
            $semanticResults = $this->semanticSearchExecutor->execute($repository, $query, $limit * 2, $fileTypes);

            $merged = $this->resultMerger->merge($keywordResults, $semanticResults, $limit);

            Log::debug('Hybrid search completed', [
                'repository_id' => $repository->id,
                'keyword_results' => count($keywordResults),
                'semantic_results' => count($semanticResults),
                'merged_results' => count($merged),
            ]);

            return $merged;
        });
    }

    /**
     * Search code using keyword matching only.
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function keywordSearch(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array
    {
        $cacheKey = $this->cacheKeyFactory->build('keyword', $repository->id, $query, $limit, $fileTypes);

        /** @var array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->keywordSearchExecutor->execute($repository, $query, $limit, $fileTypes));
    }

    /**
     * Search code using semantic similarity only.
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function semanticSearch(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array
    {
        $cacheKey = $this->cacheKeyFactory->build('semantic', $repository->id, $query, $limit, $fileTypes);

        /** @var array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->semanticSearchExecutor->execute($repository, $query, $limit, $fileTypes));
    }

    /**
     * Find code by symbol name (class, method, function).
     *
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: string, content: string, metadata: array<string, mixed>}>
     */
    public function findSymbol(Repository $repository, string $symbolName, int $limit = 5): array
    {
        $cacheKey = $this->cacheKeyFactory->build('symbol', $repository->id, $symbolName, $limit, null);

        /** @var array<int, array{file_path: string, symbol_name: string, chunk_type: string, content: string, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->symbolSearchExecutor->execute($repository, $symbolName, $limit));
    }
}
