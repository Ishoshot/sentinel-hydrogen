<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Strategies;

use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use App\Services\CodeIndexing\Mappers\SemanticSearchResultRowMapper;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

final readonly class SemanticCodeSearchStrategy
{
    /**
     * Create a new SemanticCodeSearchStrategy instance.
     */
    public function __construct(
        private EmbeddingServiceContract $embeddingService,
        private SemanticSearchResultRowMapper $rowMapper,
    ) {}

    /**
     * @param  array<string>|null  $fileTypes
     * @return array<int, array{file_path: string, content: string, score: float, metadata: array<string, mixed>}>
     */
    public function execute(Repository $repository, string $query, int $limit, ?array $fileTypes, ?CodeIndexScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeIndexScope::baseline();
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
            ->whereNotNull('code_embeddings.embedding')
            ->where('code_indexes.scope_type', $resolvedScope->type->value)
            ->where('code_indexes.scope_ref', $resolvedScope->ref);

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

        return $results
            ->map(fn (stdClass $row): array => $this->rowMapper->map($row))
            ->all();
    }
}
