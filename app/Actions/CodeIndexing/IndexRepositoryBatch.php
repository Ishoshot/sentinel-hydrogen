<?php

declare(strict_types=1);

namespace App\Actions\CodeIndexing;

use App\Enums\Queue\Queue;
use App\Jobs\CodeIndexing\GenerateCodeEmbeddingsJob;
use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class IndexRepositoryBatch
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private CodeIndexingServiceContract $indexingService,
    ) {}

    /**
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     */
    public function handle(Repository $repository, string $commitSha, array $files, ?CodeIndexScope $scope = null): void
    {
        $resolvedScope = $scope ?? CodeIndexScope::baseline();
        $installation = $repository->installation;

        if ($installation === null) {
            Log::warning('Cannot index batch without installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        Log::info('Processing index batch', [
            'repository_id' => $repository->id,
            'commit_sha' => $commitSha,
            'files_count' => count($files),
            'scope_type' => $resolvedScope->type->value,
            'scope_ref' => $resolvedScope->ref,
        ]);

        $indexed = 0;
        $failed = 0;
        $codeIndexIds = [];

        foreach ($files as $file) {
            try {
                $content = $this->fetchFileContent(
                    repository: $repository,
                    installationId: $installation->installation_id,
                    filePath: $file['path'],
                    commitSha: $commitSha,
                );

                if ($content === null) {
                    $failed++;

                    continue;
                }

                $result = $this->indexingService->indexFile($repository, $commitSha, $file['path'], $content, $resolvedScope);

                if ($result['indexed']) {
                    $indexed++;

                    $codeIndex = CodeIndex::query()
                        ->where('repository_id', $repository->id)
                        ->forScope($resolvedScope)
                        ->where('file_path', $file['path'])
                        ->first();

                    if ($codeIndex !== null) {
                        $codeIndexIds[] = $codeIndex->id;
                    }
                }
            } catch (Throwable $throwable) {
                Log::warning('Failed to index file', [
                    'repository_id' => $repository->id,
                    'file_path' => $file['path'],
                    'error' => $throwable->getMessage(),
                ]);

                $failed++;
            }
        }

        Log::info('Completed index batch', [
            'repository_id' => $repository->id,
            'indexed' => $indexed,
            'failed' => $failed,
            'scope_type' => $resolvedScope->type->value,
            'scope_ref' => $resolvedScope->ref,
        ]);

        if ($codeIndexIds !== []) {
            GenerateCodeEmbeddingsJob::dispatch($repository, $codeIndexIds)
                ->onQueue(Queue::CodeIndexing->value);
        }
    }

    /**
     * Fetch raw file content for a repository path at a specific commit SHA.
     */
    private function fetchFileContent(
        Repository $repository,
        int $installationId,
        string $filePath,
        string $commitSha,
    ): ?string {
        try {
            $response = $this->githubApi->getFileContents(
                $installationId,
                $repository->owner,
                $repository->name,
                $filePath,
                $commitSha
            );

            if (is_array($response)) {
                $content = $response['content'] ?? null;
                $encoding = $response['encoding'] ?? 'base64';

                if ($content === null) {
                    return null;
                }

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
