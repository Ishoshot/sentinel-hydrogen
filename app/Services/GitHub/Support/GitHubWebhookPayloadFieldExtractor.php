<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

final class GitHubWebhookPayloadFieldExtractor
{
    /**
     * Extract installation ID from a GitHub webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function installationId(array $payload): ?int
    {
        /** @var array{id: int}|null $installation */
        $installation = $payload['installation'] ?? null;

        return $installation['id'] ?? null;
    }

    /**
     * Extract action from a GitHub webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function action(array $payload): ?string
    {
        /** @var string|null $action */
        $action = $payload['action'] ?? null;

        return $action;
    }
}
