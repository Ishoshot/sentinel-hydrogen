<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use App\Actions\GitHub\ExtractPushChanges;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use Illuminate\Support\Facades\Log;

final readonly class PushWebhookCodeIndexingTrigger
{
    /**
     * Create a new trigger instance.
     */
    public function __construct(
        private CodeIndexingServiceContract $indexingService,
        private ExtractPushChanges $extractPushChanges,
    ) {}

    /**
     * Trigger incremental indexing for changed files in a push payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function trigger(Repository $repository, array $payload): void
    {
        $commitSha = $payload['after'] ?? null;

        if ($commitSha === null) {
            return;
        }

        $changedFiles = $this->extractPushChanges->files($payload);
        $totalChanges = count($changedFiles['added']) + count($changedFiles['modified']) + count($changedFiles['removed']);

        if ($totalChanges === 0) {
            Log::debug('No changed files detected in push, skipping indexing', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        Log::info('Triggering incremental code indexing', [
            'repository_id' => $repository->id,
            'commit_sha' => $commitSha,
            'added' => count($changedFiles['added']),
            'modified' => count($changedFiles['modified']),
            'removed' => count($changedFiles['removed']),
        ]);

        $this->indexingService->indexChangedFiles($repository, $commitSha, $changedFiles);
    }
}
