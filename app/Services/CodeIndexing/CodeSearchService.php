<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\CodeIndexing\Strategies\HybridSearchResultMergeStrategy;
use App\Services\CodeIndexing\Strategies\KeywordCodeSearchStrategy;
use App\Services\CodeIndexing\Strategies\SemanticCodeSearchStrategy;
use App\Services\CodeIndexing\Strategies\SymbolCodeSearchStrategy;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use App\Services\CodeIndexing\ValueObjects\CodeSearchScope;
use BackedEnum;
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
        private KeywordCodeSearchStrategy $keywordSearchStrategy,
        private SemanticCodeSearchStrategy $semanticSearchStrategy,
        private SymbolCodeSearchStrategy $symbolSearchStrategy,
        private HybridSearchResultMergeStrategy $resultMergeStrategy,
    ) {}

    /**
     * Search code using hybrid search (keyword + semantic).
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}>
     */
    public function search(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null, ?CodeSearchScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeSearchScope::baseline();
        $cacheKey = $this->buildCacheKey('hybrid', $repository->id, $query, $limit, $fileTypes, $resolvedScope);

        /** @var array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes, $resolvedScope): array {
            Log::debug('Performing hybrid search', [
                'repository_id' => $repository->id,
                'query' => $query,
                'limit' => $limit,
                'scope' => $resolvedScope->cacheKeySuffix(),
            ]);

            $keywordResults = $this->resolveScopedKeywordResults($repository, $query, $limit * 2, $fileTypes, $resolvedScope);
            $semanticResults = $this->resolveScopedSemanticResults($repository, $query, $limit * 2, $fileTypes, $resolvedScope);

            $merged = $this->resultMergeStrategy->merge($keywordResults, $semanticResults, $limit);

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
    public function keywordSearch(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null, ?CodeSearchScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeSearchScope::baseline();
        $cacheKey = $this->buildCacheKey('keyword', $repository->id, $query, $limit, $fileTypes, $resolvedScope);

        /** @var array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}> */
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->resolveScopedKeywordResults($repository, $query, $limit, $fileTypes, $resolvedScope)
        );
    }

    /**
     * Search code using semantic similarity only.
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function semanticSearch(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null, ?CodeSearchScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeSearchScope::baseline();
        $cacheKey = $this->buildCacheKey('semantic', $repository->id, $query, $limit, $fileTypes, $resolvedScope);

        /** @var array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}> */
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->resolveScopedSemanticResults($repository, $query, $limit, $fileTypes, $resolvedScope)
        );
    }

    /**
     * Find code by symbol name (class, method, function).
     *
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>
     */
    public function findSymbol(Repository $repository, string $symbolName, int $limit = 5, ?CodeSearchScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeSearchScope::baseline();
        $cacheKey = $this->buildCacheKey('symbol', $repository->id, $symbolName, $limit, null, $resolvedScope);

        /** @var array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}> */
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->resolveScopedSymbolResults($repository, $symbolName, $limit, $resolvedScope)
        );
    }

    /**
     * @param  array<string>|null  $fileTypes
     */
    private function buildCacheKey(string $type, int $repositoryId, string $query, int $limit, ?array $fileTypes, CodeSearchScope $scope): string
    {
        $fileTypesHash = $fileTypes !== null ? hash('xxh128', implode(',', $fileTypes)) : 'all';

        return sprintf(
            'code_search:%s:%d:%s:%d:%s:%s',
            $type,
            $repositoryId,
            hash('xxh128', $query),
            $limit,
            $fileTypesHash,
            hash('xxh128', $scope->cacheKeySuffix()),
        );
    }

    /**
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    private function resolveScopedKeywordResults(
        Repository $repository,
        string $query,
        int $limit,
        ?array $fileTypes,
        CodeSearchScope $scope
    ): array {
        if (! $scope->hasPullRequestScope()) {
            return $this->keywordSearchStrategy->execute($repository, $query, $limit, $fileTypes, CodeIndexScope::baseline());
        }

        $pullRequestScope = $scope->pullRequestScope;

        if (! $pullRequestScope instanceof CodeIndexScope) {
            return $this->keywordSearchStrategy->execute($repository, $query, $limit, $fileTypes, CodeIndexScope::baseline());
        }

        $prResults = $this->keywordSearchStrategy->execute($repository, $query, $limit, $fileTypes, $pullRequestScope);

        if (! $scope->includeBaseline) {
            return array_slice($prResults, 0, $limit);
        }

        $baselineResults = $this->keywordSearchStrategy->execute($repository, $query, $limit, $fileTypes, CodeIndexScope::baseline());

        return $this->mergeByFilePath($prResults, $baselineResults, $limit);
    }

    /**
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    private function resolveScopedSemanticResults(
        Repository $repository,
        string $query,
        int $limit,
        ?array $fileTypes,
        CodeSearchScope $scope
    ): array {
        if (! $scope->hasPullRequestScope()) {
            return $this->semanticSearchStrategy->execute($repository, $query, $limit, $fileTypes, CodeIndexScope::baseline());
        }

        $pullRequestScope = $scope->pullRequestScope;

        if (! $pullRequestScope instanceof CodeIndexScope) {
            return $this->semanticSearchStrategy->execute($repository, $query, $limit, $fileTypes, CodeIndexScope::baseline());
        }

        $prResults = $this->semanticSearchStrategy->execute($repository, $query, $limit, $fileTypes, $pullRequestScope);

        if (! $scope->includeBaseline) {
            return array_slice($prResults, 0, $limit);
        }

        $baselineResults = $this->semanticSearchStrategy->execute($repository, $query, $limit, $fileTypes, CodeIndexScope::baseline());

        return $this->mergeByFilePath($prResults, $baselineResults, $limit);
    }

    /**
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>
     */
    private function resolveScopedSymbolResults(
        Repository $repository,
        string $symbolName,
        int $limit,
        CodeSearchScope $scope
    ): array {
        if (! $scope->hasPullRequestScope()) {
            return $this->symbolSearchStrategy->execute($repository, $symbolName, $limit, CodeIndexScope::baseline());
        }

        $pullRequestScope = $scope->pullRequestScope;

        if (! $pullRequestScope instanceof CodeIndexScope) {
            return $this->symbolSearchStrategy->execute($repository, $symbolName, $limit, CodeIndexScope::baseline());
        }

        $prResults = $this->symbolSearchStrategy->execute($repository, $symbolName, $limit, $pullRequestScope);

        if (! $scope->includeBaseline) {
            return array_slice($prResults, 0, $limit);
        }

        $baselineResults = $this->symbolSearchStrategy->execute($repository, $symbolName, $limit, CodeIndexScope::baseline());

        return $this->mergeSymbolsByIdentity($prResults, $baselineResults, $limit);
    }

    /**
     * @param  array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>  $primary
     * @param  array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>  $fallback
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    private function mergeByFilePath(array $primary, array $fallback, int $limit): array
    {
        $merged = [];
        $seen = [];

        foreach ([$primary, $fallback] as $results) {
            foreach ($results as $result) {
                $key = $result['file_path'];

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $merged[] = $result;

                if (count($merged) >= $limit) {
                    return $merged;
                }
            }
        }

        return $merged;
    }

    /**
     * @param  array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>  $primary
     * @param  array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>  $fallback
     * @return array<int, array{file_path: string, symbol_name: string, chunk_type: \App\Enums\CodeIndexing\ChunkType|string, content: string, metadata: array<string, mixed>}>
     */
    private function mergeSymbolsByIdentity(array $primary, array $fallback, int $limit): array
    {
        $merged = [];
        $seen = [];

        foreach ([$primary, $fallback] as $results) {
            foreach ($results as $result) {
                $chunkType = $result['chunk_type'];
                $chunkTypeKey = $chunkType instanceof BackedEnum ? (string) $chunkType->value : (string) $chunkType;
                $key = sprintf('%s|%s|%s', $result['file_path'], $result['symbol_name'], $chunkTypeKey);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $merged[] = $result;

                if (count($merged) >= $limit) {
                    return $merged;
                }
            }
        }

        return $merged;
    }
}
