<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

/**
 * Builds incremental indexing plans from push changed-file payloads.
 */
final readonly class CodeIndexingChangeSetPlanner
{
    /**
     * Create a new planner instance.
     */
    public function __construct(
        private IncrementalChangeSetPreparer $changeSetPreparer,
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

        $indexableFiles = $this->changeSetPreparer->prepare($added, $modified);

        return [
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
            'indexable_files' => $indexableFiles,
            'requires_full_reindex' => $this->changeSetPreparer->exceedsThreshold($indexableFiles),
        ];
    }
}
