<?php

declare(strict_types=1);

namespace App\Services\GitHub\Contracts;

use App\Enums\GitHub\GitHubWebhookEvent;
use App\Services\GitHub\ValueObjects\InstallationRepositoriesWebhookPayload;
use App\Services\GitHub\ValueObjects\InstallationWebhookPayload;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;

/**
 * Contract for parsing and validating GitHub webhook payloads.
 */
interface GitHubWebhookServiceContract
{
    /**
     * Verify the webhook signature from GitHub.
     *
     * @param  string  $payload  The raw request body
     * @param  string  $signature  The X-Hub-Signature-256 header value
     * @return bool True if the signature is valid
     */
    public function verifySignature(string $payload, string $signature): bool;

    /**
     * Parse the webhook event type.
     *
     * @param  string  $eventHeader  The X-GitHub-Event header value
     */
    public function parseEventType(string $eventHeader): ?GitHubWebhookEvent;

    /**
     * Extract the installation ID from a webhook payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function extractInstallationId(array $payload): ?int;

    /**
     * Extract the action from a webhook payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function extractAction(array $payload): ?string;

    /**
     * Parse installation event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function parseInstallationPayload(array $payload): InstallationWebhookPayload;

    /**
     * Parse installation repositories event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function parseInstallationRepositoriesPayload(array $payload): InstallationRepositoriesWebhookPayload;

    /**
     * Parse pull request event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function parsePullRequestPayload(array $payload): PullRequestWebhookPayload;

    /**
     * Check if a pull request action should trigger a review.
     */
    public function shouldTriggerReview(string $action): bool;

    /**
     * Check if a pull request action should sync metadata on an existing run.
     */
    public function shouldSyncMetadata(string $action): bool;

    /**
     * Check if a pull request action should clean up temporary PR indexes.
     */
    public function shouldCleanupPreIndex(string $action): bool;
}
