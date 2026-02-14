<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Resolvers;

use App\Actions\GitHub\Support\PushWebhookPayloadContext;
use App\Services\Logging\LogContext;

final class PushWebhookPayloadContextResolver
{
    /**
     * Resolve key context from a push webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload): PushWebhookPayloadContext
    {
        $ref = $payload['ref'] ?? '';

        /** @var array{id?: int}|null $installationData */
        $installationData = $payload['installation'] ?? null;
        $installationId = $installationData['id'] ?? null;

        /** @var array{id?: int, full_name?: string}|null $repositoryData */
        $repositoryData = $payload['repository'] ?? null;
        $repositoryId = $repositoryData['id'] ?? null;
        $repositoryFullName = $repositoryData['full_name'] ?? 'unknown';

        return new PushWebhookPayloadContext(
            ref: $ref,
            installationId: $installationId,
            repositoryId: $repositoryId,
            repositoryFullName: $repositoryFullName,
            logContext: LogContext::forWebhook($installationId, $repositoryFullName, 'push'),
        );
    }
}
