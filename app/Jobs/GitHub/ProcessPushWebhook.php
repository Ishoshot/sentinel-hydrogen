<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\Enums\Queue;
use App\Models\Installation;
use App\Models\Repository;
use App\Services\Logging\LogContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Process push webhooks to sync Sentinel config when relevant files change.
 */
final class ProcessPushWebhook implements ShouldQueue
{
    use Queueable;

    private const string CONFIG_PATH_PREFIX = '.sentinel/';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {
        $this->onQueue(Queue::Webhooks->value);
    }

    /**
     * Execute the job.
     */
    public function handle(
        SyncRepositorySentinelConfig $syncConfig,
    ): void {
        $ref = $this->payload['ref'] ?? '';

        /** @var array{id?: int}|null $installationData */
        $installationData = $this->payload['installation'] ?? null;
        $installationId = $installationData['id'] ?? null;

        /** @var array{id?: int, full_name?: string}|null $repositoryData */
        $repositoryData = $this->payload['repository'] ?? null;
        $repositoryId = $repositoryData['id'] ?? null;
        $repositoryFullName = $repositoryData['full_name'] ?? 'unknown';

        $webhookCtx = LogContext::forWebhook($installationId, $repositoryFullName, 'push');

        Log::debug('Processing push webhook', array_merge($webhookCtx, ['ref' => $ref]));

        if ($installationId === null || $repositoryId === null) {
            Log::warning('Push webhook missing installation or repository data', $webhookCtx);

            return;
        }

        $installation = Installation::where('installation_id', $installationId)->first();

        if ($installation === null) {
            Log::warning('Installation not found for push webhook', $webhookCtx);

            return;
        }

        $repository = Repository::where('installation_id', $installation->id)
            ->where('github_id', $repositoryId)
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for push webhook', array_merge($webhookCtx, [
                'github_repository_id' => $repositoryId,
            ]));

            return;
        }

        $ctx = LogContext::fromRepository($repository);

        // Check if push is to the default branch
        $expectedRef = sprintf('refs/heads/%s', $repository->default_branch);
        if ($ref !== $expectedRef) {
            Log::debug('Push is not to default branch, skipping config sync', array_merge($ctx, [
                'ref' => $ref,
                'default_branch' => $repository->default_branch,
            ]));

            return;
        }

        // Check if any commits modified .sentinel/ files
        if (! $this->hasConfigChanges()) {
            Log::debug('No .sentinel/ changes in push, skipping config sync', $ctx);

            return;
        }

        Log::info('Syncing Sentinel config due to push to default branch', array_merge($ctx, ['ref' => $ref]));

        $result = $syncConfig->handle($repository);

        if ($result['synced']) {
            Log::info('Sentinel config synced successfully from push', array_merge($ctx, [
                'has_config' => $result['config'] !== null,
            ]));
        } else {
            Log::warning('Failed to sync Sentinel config from push', array_merge($ctx, [
                'error' => $result['error'],
            ]));
        }
    }

    /**
     * Check if the push contains changes to .sentinel/ directory.
     */
    private function hasConfigChanges(): bool
    {
        $commits = $this->payload['commits'] ?? [];

        if (! is_array($commits)) {
            return false;
        }

        foreach ($commits as $commit) {
            if (! is_array($commit)) {
                continue;
            }

            // Check added, modified, and removed files
            foreach (['added', 'modified', 'removed'] as $changeType) {
                $files = $commit[$changeType] ?? [];

                if (! is_array($files)) {
                    continue;
                }

                foreach ($files as $file) {
                    if (is_string($file) && str_starts_with($file, self::CONFIG_PATH_PREFIX)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
