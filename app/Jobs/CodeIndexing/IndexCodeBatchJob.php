<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Enums\Queue\Queue;
use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a batch of files to index from a repository.
 */
final class IndexCodeBatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     */
    public function __construct(
        public Repository $repository,
        public string $commitSha,
        public array $files,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(
        GitHubApiServiceContract $githubApi,
        CodeIndexingServiceContract $indexingService,
    ): void {
        $installation = $this->repository->installation;
        if ($installation === null) {
            Log::warning('Cannot index batch without installation', [
                'repository_id' => $this->repository->id,
            ]);

            return;
        }

        Log::info('Processing index batch', [
            'repository_id' => $this->repository->id,
            'commit_sha' => $this->commitSha,
            'files_count' => count($this->files),
        ]);

        $indexed = 0;
        $failed = 0;
        $codeIndexIds = [];

        foreach ($this->files as $file) {
            try {
                $content = $this->fetchFileContent(
                    $githubApi,
                    $installation->installation_id,
                    $file['path']
                );

                if ($content === null) {
                    $failed++;

                    continue;
                }

                $result = $indexingService->indexFile(
                    $this->repository,
                    $this->commitSha,
                    $file['path'],
                    $content
                );

                if ($result['indexed']) {
                    $indexed++;

                    // Get the code index ID for embedding generation
                    $codeIndex = CodeIndex::where('repository_id', $this->repository->id)
                        ->where('file_path', $file['path'])
                        ->first();

                    if ($codeIndex !== null) {
                        $codeIndexIds[] = $codeIndex->id;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Failed to index file', [
                    'repository_id' => $this->repository->id,
                    'file_path' => $file['path'],
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        Log::info('Completed index batch', [
            'repository_id' => $this->repository->id,
            'indexed' => $indexed,
            'failed' => $failed,
        ]);

        // Dispatch embedding generation job for indexed files
        if ($codeIndexIds !== []) {
            GenerateCodeEmbeddingsJob::dispatch($this->repository, $codeIndexIds)
                ->onQueue(Queue::CodeIndexing->value);
        }
    }

    /**
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(
        GitHubApiServiceContract $githubApi,
        int $installationId,
        string $filePath,
    ): ?string {
        try {
            $response = $githubApi->getFileContents(
                $installationId,
                $this->repository->owner,
                $this->repository->name,
                $filePath,
                $this->commitSha
            );

            // Response can be array with content or string
            if (is_array($response)) {
                $content = $response['content'] ?? null;
                $encoding = $response['encoding'] ?? 'base64';

                if ($content === null) {
                    return null;
                }

                // Decode base64 content
                if ($encoding === 'base64') {
                    $decoded = base64_decode((string) $content, true);

                    return $decoded === false ? null : $decoded;
                }

                return (string) $content;
            }

            return $response;
        } catch (Throwable $throwable) {
            Log::debug('Failed to fetch file content', [
                'file_path' => $filePath,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
