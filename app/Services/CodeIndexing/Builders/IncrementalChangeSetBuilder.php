<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Builders;

use App\Models\Repository;
use App\Services\CodeIndexing\Policies\AdaptiveIndexingLimitPolicy;
use App\Services\CodeIndexing\Policies\IndexableFilePolicy;

/**
 * Prepares incremental change sets for indexing by merging, filtering, and
 * detecting when a full reindex is needed.
 */
final readonly class IncrementalChangeSetBuilder
{
    /**
     * Create a new IncrementalChangeSetBuilder instance.
     */
    public function __construct(
        private IndexableFilePolicy $filePolicy,
        private AdaptiveIndexingLimitPolicy $indexingLimitPolicy = new AdaptiveIndexingLimitPolicy,
    ) {}

    /**
     * @param  array{added: array<string>, modified: array<string>, removed: array<string>}  $changedFiles
     * @return array{
     *   added: array<string>,
     *   modified: array<string>,
     *   removed: array<string>,
     *   indexable_files: array<int, array{path: string, type: string}>,
     *   requires_full_reindex: bool,
     *   indexing_limits: array{full_reindex_threshold: int, batch_size: int, tier: string, volume_bucket: string, source: string, adaptive: bool}
     * }
     */
    public function plan(array $changedFiles, Repository $repository): array
    {
        $added = $changedFiles['added'];
        $modified = $changedFiles['modified'];
        $removed = $changedFiles['removed'];
        $indexableFiles = $this->prepare($added, $modified);
        $indexingLimits = $this->indexingLimitPolicy->resolve($repository, count($indexableFiles));

        return [
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
            'indexable_files' => $indexableFiles,
            'requires_full_reindex' => $this->exceedsThreshold($indexableFiles, $indexingLimits['full_reindex_threshold']),
            'indexing_limits' => $indexingLimits,
        ];
    }

    /**
     * Prepare the indexable file set from added and modified paths.
     *
     * @param  array<string>  $added
     * @param  array<string>  $modified
     * @return array<int, array{path: string, type: string}>
     */
    public function prepare(array $added, array $modified): array
    {
        $filesToIndex = array_unique(array_merge($added, $modified));

        return array_values(array_filter(
            array_map(fn (string $path): array => ['path' => $path, 'type' => 'blob'], $filesToIndex),
            fn (array $file): bool => $this->filePolicy->shouldIndex($file['path'])
        ));
    }

    /**
     * Determine if the change set exceeds the full-reindex threshold.
     *
     * @param  array<int, array{path: string, type: string}>  $indexableFiles
     */
    public function exceedsThreshold(array $indexableFiles, int $fullReindexThreshold): bool
    {
        return count($indexableFiles) > max(1, $fullReindexThreshold);
    }
}
