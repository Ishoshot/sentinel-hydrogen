<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Enums\CodeIndexing\ChunkType;
use App\Models\CodeEmbedding;
use App\Models\CodeIndex;
use Illuminate\Support\Facades\DB;

/**
 * Persists generated embeddings for indexed source chunks.
 */
final class CodeEmbeddingPersister
{
    /**
     * Replace existing embeddings for a code index with newly generated vectors.
     *
     * @param  array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>  $chunks
     * @param  array<int, array<int, float|int>>  $embeddings
     */
    public function replace(CodeIndex $codeIndex, array $chunks, array $embeddings): int
    {
        return DB::transaction(function () use ($codeIndex, $chunks, $embeddings): int {
            CodeEmbedding::query()
                ->where('code_index_id', $codeIndex->id)
                ->delete();

            $insertedIds = $this->insertChunks($codeIndex, $chunks);
            $this->persistVectors($insertedIds, $embeddings);

            return count($insertedIds);
        });
    }

    /**
     * @param  array<int, array{type: ChunkType, symbol_name: string|null, content: string, metadata: array<string, mixed>}>  $chunks
     * @return array<int, int>
     */
    private function insertChunks(CodeIndex $codeIndex, array $chunks): array
    {
        $now = now();
        $rows = [];

        foreach ($chunks as $chunk) {
            $rows[] = [
                'code_index_id' => $codeIndex->id,
                'repository_id' => $codeIndex->repository_id,
                'chunk_type' => $chunk['type']->value,
                'symbol_name' => $chunk['symbol_name'],
                'content' => $chunk['content'],
                'metadata' => json_encode($chunk['metadata'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ];
        }

        CodeEmbedding::query()->insert($rows);

        /** @var array<int, mixed> $ids */
        $ids = CodeEmbedding::query()
            ->where('code_index_id', $codeIndex->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }

    /**
     * @param  array<int, int>  $embeddingIds
     * @param  array<int, array<int, float|int>>  $embeddings
     */
    private function persistVectors(array $embeddingIds, array $embeddings): void
    {
        if ($embeddingIds === [] || DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $caseFragments = [];
        $bindings = [];

        foreach ($embeddingIds as $index => $embeddingId) {
            if (! isset($embeddings[$index])) {
                continue;
            }

            $caseFragments[] = 'WHEN id = ? THEN ?::vector';
            $bindings[] = $embeddingId;
            $bindings[] = '['.implode(',', $embeddings[$index]).']';
        }

        if ($caseFragments === []) {
            return;
        }

        $inPlaceholders = implode(',', array_fill(0, count($embeddingIds), '?'));
        $bindings = [...$bindings, ...$embeddingIds];

        DB::update(
            sprintf(
                'UPDATE code_embeddings SET embedding = CASE %s END WHERE id IN (%s)',
                implode(' ', $caseFragments),
                $inPlaceholders
            ),
            $bindings
        );
    }
}
