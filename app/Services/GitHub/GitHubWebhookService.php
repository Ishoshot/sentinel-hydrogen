<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Enums\GitHubWebhookEvent;

final class GitHubWebhookService
{
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
        /** @var array{id: int, account: array{type: string, login: string, avatar_url?: string|null}, permissions?: array<string, string>, events?: array<int, string>} $installation */
        $installation = $payload['installation'];
        $account = $installation['account'];

        /** @var string $action */
        $action = $payload['action'];

        return [
            'action' => $action,
            'installation_id' => $installation['id'],
            'account_type' => $account['type'],
            'account_login' => $account['login'],
            'account_avatar_url' => $account['avatar_url'] ?? null,
            'permissions' => $installation['permissions'] ?? [],
            'events' => $installation['events'] ?? [],
        ];
    }

    /**
     * Parse installation repositories event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, repositories_added: array<int, array{id: int, name: string, full_name: string, private: bool}>, repositories_removed: array<int, array{id: int, name: string, full_name: string}>}
     */
    public function parseInstallationRepositoriesPayload(array $payload): array
    {
        /** @var string $action */
        $action = $payload['action'];

        /** @var array{id: int} $installation */
        $installation = $payload['installation'];

        /** @var array<int, array{id: int, name: string, full_name: string, private: bool}> $repositoriesAdded */
        $repositoriesAdded = $payload['repositories_added'] ?? [];

        /** @var array<int, array{id: int, name: string, full_name: string}> $repositoriesRemoved */
        $repositoriesRemoved = $payload['repositories_removed'] ?? [];

        return [
            'action' => $action,
            'installation_id' => $installation['id'],
            'repositories_added' => $repositoriesAdded,
            'repositories_removed' => $repositoriesRemoved,
        ];
    }

    /**
     * Parse pull request event payload.
     *
     * @param  array<string, mixed>  $payload  The webhook payload
     * @return array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function parsePullRequestPayload(array $payload): array
    {
        /** @var array{number: int, title: string, body: string|null, draft?: bool, user: array{login: string, avatar_url?: string|null}, base: array{ref: string}, head: array{ref: string, sha: string}, assignees?: array<int, array{login: string, avatar_url?: string|null}>, requested_reviewers?: array<int, array{login: string, avatar_url?: string|null}>, labels?: array<int, array{name: string, color: string}>} $pullRequest */
        $pullRequest = $payload['pull_request'];

        /** @var string $action */
        $action = $payload['action'];

        /** @var array{id: int} $installation */
        $installation = $payload['installation'];

        /** @var array{id: int, full_name: string} $repository */
        $repository = $payload['repository'];

        /** @var array{login: string} $sender */
        $sender = $payload['sender'];

        return [
            'action' => $action,
            'installation_id' => $installation['id'],
            'repository_id' => $repository['id'],
            'repository_full_name' => $repository['full_name'],
            'pull_request_number' => $pullRequest['number'],
            'pull_request_title' => $pullRequest['title'],
            'pull_request_body' => $pullRequest['body'],
            'base_branch' => $pullRequest['base']['ref'],
            'head_branch' => $pullRequest['head']['ref'],
            'head_sha' => $pullRequest['head']['sha'],
            'sender_login' => $sender['login'],
            'author' => [
                'login' => $pullRequest['user']['login'],
                'avatar_url' => $pullRequest['user']['avatar_url'] ?? null,
            ],
            'is_draft' => $pullRequest['draft'] ?? false,
            'assignees' => $this->extractUsers($pullRequest['assignees'] ?? []),
            'reviewers' => $this->extractUsers($pullRequest['requested_reviewers'] ?? []),
            'labels' => $this->extractLabels($pullRequest['labels'] ?? []),
        ];
    }

    /**
     * Check if a pull request action should trigger a review.
     */
    public function shouldTriggerReview(string $action): bool
    {
        return in_array($action, ['opened', 'synchronize', 'reopened'], true);
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
        return in_array($action, [
            'labeled',
            'unlabeled',
            'assigned',
            'unassigned',
            'review_requested',
            'review_request_removed',
            'converted_to_draft',
            'ready_for_review',
        ], true);
    }

    /**
     * Extract user information from an array of user objects.
     *
     * @param  array<int, array{login: string, avatar_url?: string|null}>  $users
     * @return array<int, array{login: string, avatar_url: string|null}>
     */
    private function extractUsers(array $users): array
    {
        return array_map(
            fn (array $user): array => [
                'login' => $user['login'],
                'avatar_url' => $user['avatar_url'] ?? null,
            ],
            $users
        );
    }

    /**
     * Extract label information from an array of label objects.
     *
     * @param  array<int, array{name: string, color: string}>  $labels
     * @return array<int, array{name: string, color: string}>
     */
    private function extractLabels(array $labels): array
    {
        return array_map(
            fn (array $label): array => [
                'name' => $label['name'],
                'color' => $label['color'],
            ],
            $labels
        );
    }
}
