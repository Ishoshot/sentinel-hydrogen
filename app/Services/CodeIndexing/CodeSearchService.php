<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Models\CodeEmbedding;
use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Service for searching indexed code using hybrid search.
 */
final readonly class CodeSearchService implements CodeSearchServiceContract
{
    private const float KEYWORD_WEIGHT = 0.6;

    private const float SEMANTIC_WEIGHT = 0.4;

    private const int CACHE_TTL = 900; // 15 minutes

    /**
     * Create a new CodeSearchService instance.
     */
    public function __construct(
        private EmbeddingServiceContract $embeddingService,
    ) {}

    /**
     * Search code using hybrid search (keyword + semantic).
     *
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}>
     */
    public function search(Repository $repository, string $query, int $limit = 10, ?array $fileTypes = null): array
    {
        $cacheKey = $this->buildCacheKey('hybrid', $repository->id, $query, $limit, $fileTypes);

        /** @var array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes): array {
            Log::debug('Performing hybrid search', [
                'repository_id' => $repository->id,
                'query' => $query,
                'limit' => $limit,
            ]);

            // Get results from both search methods
            $keywordResults = $this->keywordSearch($repository, $query, $limit * 2, $fileTypes);
            $semanticResults = $this->semanticSearch($repository, $query, $limit * 2, $fileTypes);

            // Merge and rank results
            $merged = $this->mergeResults($keywordResults, $semanticResults, $limit);

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
        $cacheKey = $this->buildCacheKey('keyword', $repository->id, $query, $limit, $fileTypes);

        /** @var array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes): array {
            $queryBuilder = CodeIndex::where('repository_id', $repository->id);

            if ($fileTypes !== null && $fileTypes !== []) {
                $queryBuilder->whereIn('file_type', $fileTypes);
            }

            // Search in file path, content, and structure
            $searchTerms = $this->extractSearchTerms($query);

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
                'content' => $this->extractRelevantContent($index->content, $searchTerms),
                'score' => $this->calculateKeywordScore($index, $searchTerms),
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
        $cacheKey = $this->buildCacheKey('semantic', $repository->id, $query, $limit, $fileTypes);

        /** @var array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}> */
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($repository, $query, $limit, $fileTypes): array {
            // Generate embedding for the query
            $queryEmbedding = $this->embeddingService->generateEmbedding($query);

            if ($queryEmbedding === []) {
                Log::warning('Failed to generate query embedding', ['query' => $query]);

                return [];
            }

            // Build the vector similarity query
            $vectorString = '['.implode(',', $queryEmbedding).']';

            // Use pgvector cosine distance for similarity search
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

            // Check if we're using PostgreSQL with pgvector
            if (DB::connection()->getDriverName() === 'pgsql') {
                $queryBuilder
                    ->selectRaw('(embedding <=> ?::vector) as distance', [$vectorString])
                    ->orderByRaw('embedding <=> ?::vector', [$vectorString]);
            } else {
                // For SQLite testing, just return results without vector similarity
                $queryBuilder->selectRaw('0.5 as distance');
            }

            $results = $queryBuilder->limit($limit)->get();

            return $results->map(function (stdClass $row): array {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                $distance = is_numeric($row->distance) ? (float) $row->distance : 0.5;

                return [
                    'file_path' => (string) $row->file_path,
                    'content' => (string) $row->content,
                    'score' => 1 - $distance, // Convert distance to similarity
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
        $cacheKey = $this->buildCacheKey('symbol', $repository->id, $symbolName, $limit, null);

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

    /**
     * Merge keyword and semantic search results.
     *
     * @param  array<int, array<string, mixed>>  $keywordResults
     * @param  array<int, array<string, mixed>>  $semanticResults
     * @return array<int, array{file_path: string, content: string, score: float, match_type: string, metadata: array<string, mixed>}>
     */
    private function mergeResults(array $keywordResults, array $semanticResults, int $limit): array
    {
        $merged = [];
        $seen = [];

        // Index keyword results by file_path
        foreach ($keywordResults as $result) {
            $filePath = (string) ($result['file_path'] ?? '');
            $content = (string) ($result['content'] ?? '');
            $rawScore = $result['score'] ?? 0.0;
            $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;
            $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

            $key = $filePath.':'.hash('xxh128', $content);
            if (! isset($seen[$key])) {
                $merged[$key] = [
                    'file_path' => $filePath,
                    'content' => $content,
                    'keyword_score' => $score,
                    'semantic_score' => 0.0,
                    'metadata' => $metadata,
                ];
                $seen[$key] = true;
            }
        }

        // Merge semantic results
        foreach ($semanticResults as $result) {
            $filePath = (string) ($result['file_path'] ?? '');
            $content = (string) ($result['content'] ?? '');
            $rawScore = $result['score'] ?? 0.0;
            $score = is_numeric($rawScore) ? (float) $rawScore : 0.0;
            $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

            $key = $filePath.':'.hash('xxh128', $content);
            if (isset($merged[$key])) {
                $merged[$key]['semantic_score'] = $score;
            } else {
                $merged[$key] = [
                    'file_path' => $filePath,
                    'content' => $content,
                    'keyword_score' => 0.0,
                    'semantic_score' => $score,
                    'metadata' => $metadata,
                ];
            }
        }

        // Calculate combined scores and determine match type
        /** @var array<string, array{file_path: string, content: string, keyword_score: float, semantic_score: float, metadata: array<string, mixed>}> $merged */
        $results = array_map(function (array $item): array {
            $keywordScore = $item['keyword_score'];
            $semanticScore = $item['semantic_score'];
            $combinedScore = ($keywordScore * self::KEYWORD_WEIGHT) + ($semanticScore * self::SEMANTIC_WEIGHT);

            $matchType = 'hybrid';
            if ($keywordScore > 0 && $semanticScore === 0.0) {
                $matchType = 'keyword';
            } elseif ($semanticScore > 0 && $keywordScore === 0.0) {
                $matchType = 'semantic';
            }

            return [
                'file_path' => $item['file_path'],
                'content' => $item['content'],
                'score' => $combinedScore,
                'match_type' => $matchType,
                'metadata' => $item['metadata'],
            ];
        }, $merged);

        // Sort by combined score and limit
        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Extract search terms from query.
     *
     * @return array<string>
     */
    private function extractSearchTerms(string $query): array
    {
        // Split by common delimiters and filter empty strings
        $terms = preg_split('/[\s,.:;()\[\]{}]+/', $query);

        if ($terms === false) {
            return [];
        }

        // Filter out very short terms and common words
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or'];

        return array_values(array_filter($terms, function (string $term) use ($stopWords): bool {
            $term = mb_strtolower(mb_trim($term));

            return mb_strlen($term) >= 2 && ! in_array($term, $stopWords, true);
        }));
    }

    /**
     * Extract relevant content snippet around search terms.
     *
     * @param  array<string>  $searchTerms
     */
    private function extractRelevantContent(string $content, array $searchTerms): string
    {
        $maxLength = 500;

        // Find the first occurrence of any search term
        $firstMatch = mb_strlen($content);
        foreach ($searchTerms as $term) {
            $pos = mb_stripos($content, (string) $term);
            if ($pos !== false && $pos < $firstMatch) {
                $firstMatch = $pos;
            }
        }

        // Extract content around the first match
        $start = max(0, $firstMatch - 100);
        $excerpt = mb_substr($content, $start, $maxLength);

        if ($start > 0) {
            $excerpt = '...'.$excerpt;
        }

        if (mb_strlen($content) > $start + $maxLength) {
            $excerpt .= '...';
        }

        return $excerpt;
    }

    /**
     * Calculate keyword match score for a code index.
     *
     * @param  array<string>  $searchTerms
     */
    private function calculateKeywordScore(CodeIndex $index, array $searchTerms): float
    {
        $score = 0.0;
        $contentLower = mb_strtolower($index->content);
        $pathLower = mb_strtolower($index->file_path);

        foreach ($searchTerms as $term) {
            $termLower = mb_strtolower($term);

            // Exact matches in file path are weighted highly
            if (str_contains($pathLower, $termLower)) {
                $score += 0.5;
            }

            // Count occurrences in content
            $count = mb_substr_count($contentLower, $termLower);
            $score += min($count * 0.1, 0.5); // Cap at 0.5 per term
        }

        // Normalize score to 0-1 range
        return min($score / count($searchTerms), 1.0);
    }

    /**
     * Build a cache key for search results.
     *
     * @param  array<string>|null  $fileTypes
     */
    private function buildCacheKey(string $type, int $repositoryId, string $query, int $limit, ?array $fileTypes): string
    {
        $fileTypesHash = $fileTypes !== null ? hash('xxh128', implode(',', $fileTypes)) : 'all';

        return sprintf(
            'code_search:%s:%d:%s:%d:%s',
            $type,
            $repositoryId,
            hash('xxh128', $query),
            $limit,
            $fileTypesHash
        );
    }
}
