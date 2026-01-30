<?php

declare(strict_types=1);

namespace App\Services\GitHub\Contracts;

use App\Enums\GitHub\GitHubWebhookEvent;

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
     * @return array{action: string, installation_id: int, account_type: string, account_login: string, account_avatar_url: string|null, permissions: array<string, string>, events: array<int, string>}
     */
    public function parseInstallationPayload(array $payload): array;

    /**
     * Parse installation repositories event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, repositories_added: array<int, array{id: int, name: string, full_name: string, private: bool}>, repositories_removed: array<int, array{id: int, name: string, full_name: string}>}
     */
    public function parseInstallationRepositoriesPayload(array $payload): array;

    /**
     * Parse pull request event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function parsePullRequestPayload(array $payload): array;

    /**
     * Check if a pull request action should trigger a review.
     */
    public function shouldTriggerReview(string $action): bool;

    /**
     * Check if a pull request action should sync metadata on an existing run.
     */
    public function shouldSyncMetadata(string $action): bool;
}
