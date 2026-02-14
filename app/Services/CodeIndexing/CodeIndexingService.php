<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\CodeIndexing\Support\IncrementalChangeSetPreparer;
use App\Services\CodeIndexing\Support\IndexableFilePolicy;
use App\Services\CodeIndexing\Support\IndexBatchDispatcher;
use App\Services\CodeIndexing\Support\RepositoryTreeFetcher;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Service for indexing repository code and extracting structure information.
 */
final readonly class CodeIndexingService implements CodeIndexingServiceContract
{
    /**
     * Create a new CodeIndexingService instance.
     */
    public function __construct(
        private SemanticAnalyzerInterface $semanticAnalyzer,
        private IndexableFilePolicy $filePolicy,
        private RepositoryTreeFetcher $treeFetcher,
        private IncrementalChangeSetPreparer $changeSetPreparer,
        private IndexBatchDispatcher $batchDispatcher,
    ) {}

    /**
     * Index a repository at a specific commit.
     */
    public function indexRepository(Repository $repository, string $commitSha): void
    {
        Log::info('Starting full repository index', [
            'repository_id' => $repository->id,
            'commit_sha' => $commitSha,
        ]);

        $installation = $repository->installation;
        if ($installation === null) {
            Log::warning('Cannot index repository without installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $tree = $this->treeFetcher->fetch($installation->installation_id, $repository->owner, $repository->name, $commitSha);

        $indexableFiles = $this->filePolicy->filterTree($tree);

        Log::info('Found indexable files', [
            'repository_id' => $repository->id,
            'total_files' => count($tree),
            'indexable_files' => count($indexableFiles),
        ]);

        $this->batchDispatcher->dispatch($repository, $commitSha, $indexableFiles);
    }

    /**
     * Index only changed files (incremental indexing).
     *
     * @param  array{added: array<string>, modified: array<string>, removed: array<string>}  $changedFiles
     */
    public function indexChangedFiles(Repository $repository, string $commitSha, array $changedFiles): void
    {
        $added = $changedFiles['added'];
        $modified = $changedFiles['modified'];
        $removed = $changedFiles['removed'];

        Log::info('Starting incremental index', [
            'repository_id' => $repository->id,
            'commit_sha' => $commitSha,
            'added' => count($added),
            'modified' => count($modified),
            'removed' => count($removed),
        ]);

        if ($removed !== []) {
            $this->removeFiles($repository, $removed);
        }

        $indexableFiles = $this->changeSetPreparer->prepare($added, $modified);

        if ($indexableFiles === []) {
            Log::debug('No indexable files in change set', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        if ($this->changeSetPreparer->exceedsThreshold($indexableFiles)) {
            Log::info('Large change set detected, triggering full reindex', [
                'repository_id' => $repository->id,
                'changed_files' => count($indexableFiles),
            ]);

            $this->indexRepository($repository, $commitSha);

            return;
        }

        $this->batchDispatcher->dispatch($repository, $commitSha, $indexableFiles);
    }

    /**
     * Index a single file.
     *
     * @return array{indexed: bool, structure: array<string, mixed>|null}
     */
    public function indexFile(Repository $repository, string $commitSha, string $filePath, string $content): array
    {
        if (! $this->filePolicy->shouldIndex($filePath)) {
            return ['indexed' => false, 'structure' => null];
        }

        $fileType = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'txt';

        $structure = $this->semanticAnalyzer->analyzeFile($content, $filePath);

        CodeIndex::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'file_path' => $filePath,
            ],
            [
                'commit_sha' => $commitSha,
                'file_type' => $fileType,
                'content' => $content,
                'structure' => $structure,
                'metadata' => [
                    'lines' => mb_substr_count($content, "\n") + 1,
                    'size' => mb_strlen($content),
                ],
                'indexed_at' => now(),
            ]
        );

        return ['indexed' => true, 'structure' => $structure];
    }

    /**
     * Remove indexed files that no longer exist.
     *
     * @param  array<string>  $filePaths
     */
    public function removeFiles(Repository $repository, array $filePaths): void
    {
        if ($filePaths === []) {
            return;
        }

        $deleted = CodeIndex::where('repository_id', $repository->id)
            ->whereIn('file_path', $filePaths)
            ->delete();

        Log::info('Removed files from index', [
            'repository_id' => $repository->id,
            'requested' => count($filePaths),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Check if a file should be indexed based on type and path.
     */
    public function shouldIndexFile(string $filePath): bool
    {
        return $this->filePolicy->shouldIndex($filePath);
    }
}
