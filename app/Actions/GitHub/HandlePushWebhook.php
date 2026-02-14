<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\Models\Installation;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;

final readonly class HandlePushWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private SyncRepositorySentinelConfig $syncConfig,
        private CodeIndexingServiceContract $indexingService,
        private ExtractPushChanges $extractPushChanges,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $ref = $payload['ref'] ?? '';

        /** @var array{id?: int}|null $installationData */
        $installationData = $payload['installation'] ?? null;
        $installationId = $installationData['id'] ?? null;

        /** @var array{id?: int, full_name?: string}|null $repositoryData */
        $repositoryData = $payload['repository'] ?? null;
        $repositoryId = $repositoryData['id'] ?? null;
        $repositoryFullName = $repositoryData['full_name'] ?? 'unknown';

        $webhookContext = LogContext::forWebhook($installationId, $repositoryFullName, 'push');

        Log::debug('Processing push webhook', array_merge($webhookContext, ['ref' => $ref]));

        if ($installationId === null || $repositoryId === null) {
            Log::warning('Push webhook missing installation or repository data', $webhookContext);

            return;
        }

        $installation = Installation::query()->where('installation_id', $installationId)->first();

        if ($installation === null) {
            Log::warning('Installation not found for push webhook', $webhookContext);

            return;
        }

        $repository = Repository::query()
            ->where('installation_id', $installation->id)
            ->where('github_id', $repositoryId)
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for push webhook', array_merge($webhookContext, [
                'github_repository_id' => $repositoryId,
            ]));

            return;
        }

        $context = LogContext::fromRepository($repository);
        $expectedRef = sprintf('refs/heads/%s', $repository->default_branch);

        if ($ref !== $expectedRef) {
            Log::debug('Push is not to default branch, skipping processing', array_merge($context, [
                'ref' => $ref,
                'default_branch' => $repository->default_branch,
            ]));

            return;
        }

        $this->triggerCodeIndexing($repository, $payload);

        if (! $this->extractPushChanges->hasConfigChanges($payload)) {
            Log::debug('No .sentinel/ changes in push, skipping config sync', $context);

            return;
        }

        Log::info('Syncing Sentinel config due to push to default branch', array_merge($context, ['ref' => $ref]));

        $result = $this->syncConfig->handle($repository);

        if ($result['synced']) {
            Log::info('Sentinel config synced successfully from push', array_merge($context, [
                'has_config' => $result['config'] !== null,
            ]));

            return;
        }

        Log::warning('Failed to sync Sentinel config from push', array_merge($context, [
            'error' => $result['error'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function triggerCodeIndexing(Repository $repository, array $payload): void
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
