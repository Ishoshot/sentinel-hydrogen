<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Builders\CodeIndexingChangeSetBuilder;
use App\Services\CodeIndexing\Builders\IncrementalChangeSetBuilder;
use App\Services\CodeIndexing\Clients\RepositoryTreeClient;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\CodeIndexing\Factories\CodeIndexingPayloadFactory;
use App\Services\CodeIndexing\Loggers\CodeIndexingTelemetryLogger;
use App\Services\CodeIndexing\Policies\IndexableFilePolicy;
use App\Services\CodeIndexing\Strategies\IndexBatchDispatchStrategy;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;

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
        private RepositoryTreeClient $treeClient,
        private IncrementalChangeSetBuilder $changeSetBuilder,
        private ?CodeIndexingChangeSetBuilder $changeSetPlanner,
        private ?CodeIndexingPayloadFactory $payloadFactory,
        private ?CodeIndexingTelemetryLogger $telemetryLogger,
        private IndexBatchDispatchStrategy $batchDispatchStrategy,
    ) {}

    /**
     * Index a repository at a specific commit.
     */
    public function indexRepository(Repository $repository, string $commitSha): void
    {
        $this->telemetryLogger()->logFullIndexStarted($repository->id, $commitSha);

        $installation = $repository->installation;
        if ($installation === null) {
            $this->telemetryLogger()->logMissingInstallation($repository->id);

            return;
        }

        $tree = $this->treeClient->resolve($installation->installation_id, $repository->owner, $repository->name, $commitSha);

        $indexableFiles = $this->filePolicy->filterTree($tree);

        $this->telemetryLogger()->logIndexableFilesDiscovered(
            repositoryId: $repository->id,
            totalFiles: count($tree),
            indexableFiles: count($indexableFiles),
        );

        $this->batchDispatchStrategy->dispatch($repository, $commitSha, $indexableFiles);
    }

    /**
     * Index only changed files (incremental indexing).
     *
     * @param  array{added: array<string>, modified: array<string>, removed: array<string>}  $changedFiles
     */
    public function indexChangedFiles(Repository $repository, string $commitSha, array $changedFiles): void
    {
        $changeSetPlan = $this->changeSetPlanner()->plan($changedFiles);

        $this->telemetryLogger()->logIncrementalIndexStarted(
            repositoryId: $repository->id,
            commitSha: $commitSha,
            added: count($changeSetPlan['added']),
            modified: count($changeSetPlan['modified']),
            removed: count($changeSetPlan['removed']),
        );

        if ($changeSetPlan['removed'] !== []) {
            $this->removeFiles($repository, $changeSetPlan['removed']);
        }

        $indexableFiles = $changeSetPlan['indexable_files'];

        if ($indexableFiles === []) {
            $this->telemetryLogger()->logNoIndexableFilesInChangeSet($repository->id);

            return;
        }

        if ($changeSetPlan['requires_full_reindex']) {
            $this->telemetryLogger()->logLargeChangeSet($repository->id, count($indexableFiles));

            $this->indexRepository($repository, $commitSha);

            return;
        }

        $this->batchDispatchStrategy->dispatch($repository, $commitSha, $indexableFiles);
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

        $structure = $this->semanticAnalyzer->analyzeFile($content, $filePath);
        $indexPayload = $this->payloadFactory()->build($commitSha, $filePath, $content, $structure);

        CodeIndex::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'file_path' => $filePath,
            ],
            $indexPayload
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

        $this->telemetryLogger()->logRemovedFiles($repository->id, count($filePaths), $deleted);
    }

    /**
     * Check if a file should be indexed based on type and path.
     */
    public function shouldIndexFile(string $filePath): bool
    {
        return $this->filePolicy->shouldIndex($filePath);
    }

    /**
     * ChangeSetPlanner.
     */
    private function changeSetPlanner(): CodeIndexingChangeSetBuilder
    {
        return $this->changeSetPlanner ?? new CodeIndexingChangeSetBuilder($this->changeSetBuilder);
    }

    /**
     * PayloadFactory.
     */
    private function payloadFactory(): CodeIndexingPayloadFactory
    {
        return $this->payloadFactory ?? new CodeIndexingPayloadFactory;
    }

    /**
     * TelemetryLogger.
     */
    private function telemetryLogger(): CodeIndexingTelemetryLogger
    {
        return $this->telemetryLogger ?? new CodeIndexingTelemetryLogger;
    }
}
