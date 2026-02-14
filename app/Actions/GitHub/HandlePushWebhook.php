<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Support\PushWebhookCodeIndexingTrigger;
use App\Actions\GitHub\Support\PushWebhookPayloadContextResolver;
use App\Actions\GitHub\Support\PushWebhookRepositoryResolver;
use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;

final readonly class HandlePushWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private SyncRepositorySentinelConfig $syncConfig,
        private ExtractPushChanges $extractPushChanges,
        private PushWebhookPayloadContextResolver $payloadContextResolver,
        private PushWebhookRepositoryResolver $repositoryResolver,
        private PushWebhookCodeIndexingTrigger $codeIndexingTrigger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $context = $this->payloadContextResolver->resolve($payload);
        $ref = $context->ref;
        $webhookContext = $context->logContext;

        Log::debug('Processing push webhook', array_merge($webhookContext, ['ref' => $ref]));

        if ($context->installationId === null || $context->repositoryId === null) {
            Log::warning('Push webhook missing installation or repository data', $webhookContext);

            return;
        }

        $resolution = $this->repositoryResolver->resolve((int) $context->installationId, (int) $context->repositoryId);
        $installation = $resolution->installation;

        if (! $installation instanceof \App\Models\Installation) {
            Log::warning('Installation not found for push webhook', $webhookContext);

            return;
        }

        $repository = $resolution->repository;

        if (! $repository instanceof \App\Models\Repository) {
            Log::warning('Repository not found for push webhook', array_merge($webhookContext, [
                'github_repository_id' => $context->repositoryId,
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

        $this->codeIndexingTrigger->trigger($repository, $payload);

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
}
