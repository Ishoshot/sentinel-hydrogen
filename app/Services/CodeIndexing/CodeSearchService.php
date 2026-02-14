<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Models\CodeEmbedding;
use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use App\Services\CodeIndexing\Support\CodeSearchCacheKeyFactory;
use App\Services\CodeIndexing\Support\HybridSearchResultMerger;
use App\Services\CodeIndexing\Support\KeywordSearchScorer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

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
        private EmbeddingServiceContract $embeddingService,
        private KeywordSearchScorer $keywordScorer,
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

            $keywordResults = $this->keywordSearch($repository, $query, $limit * 2, $fileTypes);
            $semanticResults = $this->semanticSearch($repository, $query, $limit * 2, $fileTypes);

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
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes): array {
            $queryBuilder = CodeIndex::where('repository_id', $repository->id);

            if ($fileTypes !== null && $fileTypes !== []) {
                $queryBuilder->whereIn('file_type', $fileTypes);
            }

            $searchTerms = $this->keywordScorer->extractTerms($query);

            if ($searchTerms === []) {
                return [];
            }

            $queryBuilder->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $q->orWhere('file_path', 'LIKE', '%'.$term.'%')
                        ->orWhere('content', 'LIKE', '%'.$term.'%');
                }
            });

            $results = $queryBuilder
                ->select(['id', 'file_path', 'file_type', 'content', 'structure', 'metadata'])
                ->limit($limit)
                ->get();

            return $results->map(fn (CodeIndex $index): array => [
                'file_path' => $index->file_path,
                'content' => $this->keywordScorer->extractSnippet($index->content, $searchTerms),
                'score' => $this->keywordScorer->calculateScore($index, $searchTerms),
                'metadata' => [
                    'file_type' => $index->file_type,
                    'structure' => $index->structure,
                    'match_type' => 'keyword',
                ],
            ])->sortByDesc('score')->values()->all();
        });
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
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes): array {
            $queryEmbedding = $this->embeddingService->generateEmbedding($query);

            if ($queryEmbedding === []) {
                Log::warning('Failed to generate query embedding', ['query' => $query]);

                return [];
            }

            $vectorString = '['.implode(',', $queryEmbedding).']';

            $queryBuilder = DB::table('code_embeddings')
                ->select([
                    'code_embeddings.id',
                    'code_embeddings.code_index_id',
                    'code_embeddings.chunk_type',
                    'code_embeddings.symbol_name',
                    'code_embeddings.content',
                    'code_embeddings.metadata',
                    'code_indexes.file_path',
                    'code_indexes.file_type',
                ])
                ->join('code_indexes', 'code_embeddings.code_index_id', '=', 'code_indexes.id')
                ->where('code_embeddings.repository_id', $repository->id)
                ->whereNotNull('code_embeddings.embedding');

            if ($fileTypes !== null && $fileTypes !== []) {
                $queryBuilder->whereIn('code_indexes.file_type', $fileTypes);
            }

            if (DB::connection()->getDriverName() === 'pgsql') {
                $queryBuilder
                    ->selectRaw('(embedding <=> ?::vector) as distance', [$vectorString])
                    ->orderByRaw('embedding <=> ?::vector', [$vectorString]);
            } else {
                $queryBuilder->selectRaw('0.5 as distance');
            }

            $results = $queryBuilder->limit($limit)->get();

            return $results->map(function (stdClass $row): array {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                $distance = is_numeric($row->distance) ? (float) $row->distance : 0.5;

                return [
                    'file_path' => (string) $row->file_path,
                    'content' => (string) $row->content,
                    'score' => 1 - $distance,
                    'metadata' => [
                        'file_type' => $row->file_type,
                        'chunk_type' => $row->chunk_type,
                        'symbol_name' => $row->symbol_name,
                        'match_type' => 'semantic',
                        ...(is_array($metadata) ? $metadata : []),
                    ],
                ];
            })->all();
        });
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
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $symbolName, $limit): array {
            $results = CodeEmbedding::where('repository_id', $repository->id)
                ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($symbolName): void {
                    $q->where('symbol_name', 'LIKE', '%'.$symbolName.'%')
                        ->orWhere('symbol_name', $symbolName);
                })
                ->whereIn('chunk_type', ['class', 'method', 'function'])
                ->with('codeIndex:id,file_path,file_type')
                ->limit($limit)
                ->get();

            return $results->map(fn (CodeEmbedding $embedding): array => [
                'file_path' => $embedding->codeIndex?->file_path ?? '',
                'symbol_name' => $embedding->symbol_name ?? '',
                'chunk_type' => $embedding->chunk_type,
                'content' => $embedding->content,
                'metadata' => [
                    'file_type' => $embedding->codeIndex?->file_type,
                    ...(is_array($embedding->metadata) ? $embedding->metadata : []),
                ],
            ])->all();
        });
    }
}
