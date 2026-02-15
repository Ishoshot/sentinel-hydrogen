<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Strategies;

use App\Enums\Queue\Queue;
use App\Jobs\CodeIndexing\IndexCodeBatchJob;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

/**
 * Chunks indexable files into batches and dispatches indexing jobs.
 */
final readonly class IndexBatchDispatchStrategy
{
    private const int BATCH_SIZE = 50;

    /**
     * Dispatch batch indexing jobs.
     *
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     */
    public function dispatch(Repository $repository, string $commitSha, array $files): void
    {
        $batches = array_chunk($files, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            IndexCodeBatchJob::dispatch($repository, $commitSha, $batch)
                ->onQueue(Queue::CodeIndexing->value);
        }

        Log::info('Dispatched indexing batch jobs', [
            'repository_id' => $repository->id,
            'total_files' => count($files),
            'batches' => count($batches),
        ]);
    }
}
