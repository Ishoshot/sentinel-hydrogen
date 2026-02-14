<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Enums\GitHub\GitHubWebhookEvent;
use App\Enums\GitHub\PullRequestAction;
use App\Services\GitHub\Contracts\GitHubWebhookServiceContract;
use App\Services\GitHub\Support\GitHubWebhookPayloadParser;

final readonly class GitHubWebhookService implements GitHubWebhookServiceContract
{
    /**
     * Create a new instance.
     */
    public function __construct(private GitHubWebhookPayloadParser $payloadParser = new GitHubWebhookPayloadParser) {}

    /**
     * Verify the webhook signature from GitHub.
     *
     * @param  string  $payload  The raw request body
     * @param  string  $signature  The X-Hub-Signature-256 header value
     * @return bool True if the signature is valid
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        $secret = config('github.webhook_secret');

        if (empty($secret)) {
            return false;
        }

        /** @var string $secretString */
        $secretString = $secret;
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secretString);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Parse the webhook event type.
     *
     * @param  string  $eventHeader  The X-GitHub-Event header value
     */
    public function parseEventType(string $eventHeader): ?GitHubWebhookEvent
    {
        return GitHubWebhookEvent::tryFrom($eventHeader);
    }

    /**
     * Extract the installation ID from a webhook payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function extractInstallationId(array $payload): ?int
    {
        /** @var array{id: int}|null $installation */
        $installation = $payload['installation'] ?? null;

        return $installation['id'] ?? null;
    }

    /**
     * Extract the action from a webhook payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function extractAction(array $payload): ?string
    {
        /** @var string|null $action */
        $action = $payload['action'] ?? null;

        return $action;
    }

    /**
     * Parse installation event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, account_type: string, account_login: string, account_avatar_url: string|null, permissions: array<string, string>, events: array<int, string>}
     */
    public function parseInstallationPayload(array $payload): array
    {
        return $this->payloadParser->parseInstallationPayload($payload);
    }

    /**
     * Parse installation repositories event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, repositories_added: array<int, array{id: int, name: string, full_name: string, private: bool}>, repositories_removed: array<int, array{id: int, name: string, full_name: string}>}
     */
    public function parseInstallationRepositoriesPayload(array $payload): array
    {
        return $this->payloadParser->parseInstallationRepositoriesPayload($payload);
    }

    /**
     * Parse pull request event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function parsePullRequestPayload(array $payload): array
    {
        return $this->payloadParser->parsePullRequestPayload($payload);
    }

    /**
     * Check if a pull request action should trigger a review.
     */
    public function shouldTriggerReview(string $action): bool
    {
        $prAction = PullRequestAction::tryFrom($action);

        return $prAction?->shouldTriggerReview() ?? false;
    }

    /**
     * Check if a pull request action should sync metadata on an existing run.
     *
     * These actions update PR metadata but don't require a new review:
     * - labeled/unlabeled: Label changes
     * - assigned/unassigned: Assignee changes
     * - review_requested/review_request_removed: Reviewer changes
     * - converted_to_draft/ready_for_review: Draft status changes
     */
    public function shouldSyncMetadata(string $action): bool
    {
        $prAction = PullRequestAction::tryFrom($action);

        return $prAction?->shouldSyncMetadata() ?? false;
    }
}
