<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Strategies;

use App\Enums\Queue\Queue;
use App\Jobs\CodeIndexing\IndexCodeBatchJob;
use App\Models\Repository;
use App\Services\CodeIndexing\Policies\AdaptiveIndexingLimitPolicy;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use Illuminate\Support\Facades\Log;

/**
 * Chunks indexable files into batches and dispatches indexing jobs.
 */
final readonly class IndexBatchDispatchStrategy
{
    /**
     * Create a new dispatch strategy instance.
     */
    public function __construct(
        private AdaptiveIndexingLimitPolicy $indexingLimitPolicy = new AdaptiveIndexingLimitPolicy,
    ) {}

    /**
     * Dispatch batch indexing jobs.
     *
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     */
    public function dispatch(
        Repository $repository,
        string $commitSha,
        array $files,
        ?CodeIndexScope $scope = null,
        ?int $batchSizeOverride = null
    ): void {
        $resolvedScope = $scope ?? CodeIndexScope::baseline();
        $limits = $this->indexingLimitPolicy->resolve($repository, count($files));
        $batchSize = max(1, $batchSizeOverride ?? $limits['batch_size']);
        $batches = array_chunk($files, $batchSize);

        foreach ($batches as $batch) {
            IndexCodeBatchJob::dispatch($repository, $commitSha, $batch, $resolvedScope->toArray())
                ->onQueue(Queue::CodeIndexing->value);
        }

        Log::info('Dispatched indexing batch jobs', [
            'repository_id' => $repository->id,
            'total_files' => count($files),
            'batches' => count($batches),
            'batch_size' => $batchSize,
            'batch_size_override' => $batchSizeOverride,
            'scope_type' => $resolvedScope->type->value,
            'scope_ref' => $resolvedScope->ref,
            'limit_full_reindex_threshold' => $limits['full_reindex_threshold'],
            'limit_tier' => $limits['tier'],
            'limit_volume_bucket' => $limits['volume_bucket'],
            'limit_source' => $limits['source'],
            'adaptive_limits_enabled' => $limits['adaptive'],
        ]);
    }
}
