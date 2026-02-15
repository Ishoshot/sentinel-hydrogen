<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Builders;

use App\Services\CodeIndexing\Policies\IndexableFilePolicy;

/**
 * Prepares incremental change sets for indexing by merging, filtering, and
 * detecting when a full reindex is needed.
 */
final readonly class IncrementalChangeSetBuilder
{
    private const int FULL_REINDEX_THRESHOLD = 500;

    /**
     * Create a new IncrementalChangeSetBuilder instance.
     */
    public function __construct(
        private IndexableFilePolicy $filePolicy,
    ) {}

    /**
     * @param  array{added: array<string>, modified: array<string>, removed: array<string>}  $changedFiles
     * @return array{
     *   added: array<string>,
     *   modified: array<string>,
     *   removed: array<string>,
     *   indexable_files: array<int, array{path: string, type: string}>,
     *   requires_full_reindex: bool
     * }
     */
    public function plan(array $changedFiles): array
    {
        $added = $changedFiles['added'];
        $modified = $changedFiles['modified'];
        $removed = $changedFiles['removed'];
        $indexableFiles = $this->prepare($added, $modified);

        return [
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
            'indexable_files' => $indexableFiles,
            'requires_full_reindex' => $this->exceedsThreshold($indexableFiles),
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
    public function exceedsThreshold(array $indexableFiles): bool
    {
        return count($indexableFiles) > self::FULL_REINDEX_THRESHOLD;
    }
}
