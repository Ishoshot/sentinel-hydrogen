<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Loggers;

use Illuminate\Support\Facades\Log;

/**
 * Emits structured logs for indexing flows.
 */
final readonly class CodeIndexingTelemetryLogger
{
    /**
     * LogFullIndexStarted.
     */
    public function logFullIndexStarted(int $repositoryId, string $commitSha): void
    {
        Log::info('Starting full repository index', [
            'repository_id' => $repositoryId,
            'commit_sha' => $commitSha,
        ]);
    }

    /**
     * LogMissingInstallation.
     */
    public function logMissingInstallation(int $repositoryId): void
    {
        Log::warning('Cannot index repository without installation', [
            'repository_id' => $repositoryId,
        ]);
    }

    /**
     * LogIndexableFilesDiscovered.
     */
    public function logIndexableFilesDiscovered(int $repositoryId, int $totalFiles, int $indexableFiles): void
    {
        Log::info('Found indexable files', [
            'repository_id' => $repositoryId,
            'total_files' => $totalFiles,
            'indexable_files' => $indexableFiles,
        ]);
    }

    /**
     * LogIncrementalIndexStarted.
     */
    public function logIncrementalIndexStarted(int $repositoryId, string $commitSha, int $added, int $modified, int $removed): void
    {
        Log::info('Starting incremental index', [
            'repository_id' => $repositoryId,
            'commit_sha' => $commitSha,
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
        ]);
    }

    /**
     * LogNoIndexableFilesInChangeSet.
     */
    public function logNoIndexableFilesInChangeSet(int $repositoryId): void
    {
        Log::debug('No indexable files in change set', [
            'repository_id' => $repositoryId,
        ]);
    }

    /**
     * LogLargeChangeSet.
     */
    public function logLargeChangeSet(int $repositoryId, int $changedFiles): void
    {
        Log::info('Large change set detected, triggering full reindex', [
            'repository_id' => $repositoryId,
            'changed_files' => $changedFiles,
        ]);
    }

    /**
     * LogRemovedFiles.
     */
    public function logRemovedFiles(int $repositoryId, int $requested, int $deleted): void
    {
        Log::info('Removed files from index', [
            'repository_id' => $repositoryId,
            'requested' => $requested,
            'deleted' => $deleted,
        ]);
    }
}
