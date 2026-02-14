<?php

declare(strict_types=1);

namespace App\Actions\CodeIndexing;

use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\CodeEmbeddingChunkBuilder;
use App\Services\CodeIndexing\CodeEmbeddingPersister;
use App\Services\CodeIndexing\Contracts\EmbeddingServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class GenerateEmbeddingsForCodeIndexes
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private EmbeddingServiceContract $embeddingService,
        private CodeEmbeddingChunkBuilder $chunkBuilder,
        private CodeEmbeddingPersister $embeddingPersister,
    ) {}

    /**
     * @param  array<int>  $codeIndexIds
     */
    public function handle(Repository $repository, array $codeIndexIds): void
    {
        Log::info('Generating embeddings for indexed code', [
            'repository_id' => $repository->id,
            'code_index_count' => count($codeIndexIds),
        ]);

        $totalEmbeddings = 0;

        CodeIndex::query()
            ->whereIn('id', $codeIndexIds)
            ->lazyById(100, 'id')
            ->each(function (CodeIndex $codeIndex) use (&$totalEmbeddings): void {
                try {
                    $embeddingsCreated = $this->processCodeIndex($codeIndex);
                    $totalEmbeddings += $embeddingsCreated;
                } catch (Throwable $throwable) {
                    Log::warning('Failed to generate embeddings for file', [
                        'code_index_id' => $codeIndex->id,
                        'file_path' => $codeIndex->file_path,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            });

        Log::info('Completed embedding generation', [
            'repository_id' => $repository->id,
            'files_processed' => count($codeIndexIds),
            'embeddings_created' => $totalEmbeddings,
        ]);
    }

    /**
     * Generate embeddings and persist them for a single indexed file.
     */
    private function processCodeIndex(CodeIndex $codeIndex): int
    {
        $chunks = $this->chunkBuilder->build($codeIndex);

        if ($chunks === []) {
            return 0;
        }

        $contents = array_column($chunks, 'content');
        $embeddings = $this->embeddingService->generateEmbeddings($contents);

        if (count($embeddings) !== count($chunks)) {
            Log::warning('Embedding count mismatch', [
                'code_index_id' => $codeIndex->id,
                'chunks' => count($chunks),
                'embeddings' => count($embeddings),
            ]);

            return 0;
        }

        return $this->embeddingPersister->replace($codeIndex, $chunks, $embeddings);
    }
}
