<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Enums\GitHub\GitHubWebhookEvent;
use App\Services\GitHub\Contracts\GitHubWebhookServiceContract;
use App\Services\GitHub\Parsers\GitHubWebhookPayloadParser;
use App\Services\GitHub\Policies\GitHubPullRequestActionPolicy;
use App\Services\GitHub\Policies\GitHubWebhookSignaturePolicy;
use App\Services\GitHub\ValueObjects\InstallationRepositoriesWebhookPayload;
use App\Services\GitHub\ValueObjects\InstallationWebhookPayload;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;

final readonly class GitHubWebhookService implements GitHubWebhookServiceContract
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private GitHubWebhookPayloadParser $payloadParser = new GitHubWebhookPayloadParser,
        private GitHubWebhookSignaturePolicy $signatureVerifier = new GitHubWebhookSignaturePolicy,
        private GitHubPullRequestActionPolicy $pullRequestActionPolicy = new GitHubPullRequestActionPolicy,
    ) {}

    /**
     * Verify the webhook signature from GitHub.
     *
     * @param  string  $payload  The raw request body
     * @param  string  $signature  The X-Hub-Signature-256 header value
     * @return bool True if the signature is valid
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        return $this->signatureVerifier->verify($payload, $signature);
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
        return $this->payloadParser->extractInstallationId($payload);
    }

    /**
     * Extract the action from a webhook payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function extractAction(array $payload): ?string
    {
        return $this->payloadParser->extractAction($payload);
    }

    /**
     * Parse installation event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function parseInstallationPayload(array $payload): InstallationWebhookPayload
    {
        return $this->payloadParser->parseInstallationPayload($payload);
    }

    /**
     * Parse installation repositories event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function parseInstallationRepositoriesPayload(array $payload): InstallationRepositoriesWebhookPayload
    {
        return $this->payloadParser->parseInstallationRepositoriesPayload($payload);
    }

    /**
     * Parse pull request event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     */
    public function parsePullRequestPayload(array $payload): PullRequestWebhookPayload
    {
        return $this->payloadParser->parsePullRequestPayload($payload);
    }

    /**
     * Check if a pull request action should trigger a review.
     */
    public function shouldTriggerReview(string $action): bool
    {
        return $this->pullRequestActionPolicy->shouldTriggerReview($action);
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
        return $this->pullRequestActionPolicy->shouldSyncMetadata($action);
    }

    /**
     * Check if a pull request action should clean up temporary PR index data.
     */
    public function shouldCleanupPreIndex(string $action): bool
    {
        return $this->pullRequestActionPolicy->shouldCleanupPreIndex($action);
    }
}
