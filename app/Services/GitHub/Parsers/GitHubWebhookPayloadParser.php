<?php

declare(strict_types=1);

namespace App\Services\GitHub\Parsers;

use App\Services\GitHub\ValueObjects\InstallationRepositoriesWebhookPayload;
use App\Services\GitHub\ValueObjects\InstallationWebhookPayload;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;

final readonly class GitHubWebhookPayloadParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseInstallationPayload(array $payload): InstallationWebhookPayload
    {
        /** @var array{id: int, account: array{type: string, login: string, avatar_url?: string|null}, permissions?: array<string, string>, events?: array<int, string>} $installation */
        $installation = $payload['installation'];
        $account = $installation['account'];

        /** @var string $action */
        $action = $payload['action'];

        return new InstallationWebhookPayload(
            action: $action,
            installationId: $installation['id'],
            accountType: $account['type'],
            accountLogin: $account['login'],
            accountAvatarUrl: $account['avatar_url'] ?? null,
            permissions: $installation['permissions'] ?? [],
            events: $installation['events'] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseInstallationRepositoriesPayload(array $payload): InstallationRepositoriesWebhookPayload
    {
        /** @var string $action */
        $action = $payload['action'];

        /** @var array{id: int} $installation */
        $installation = $payload['installation'];

        /** @var array<int, array{id: int, name: string, full_name: string, private: bool}> $repositoriesAdded */
        $repositoriesAdded = $payload['repositories_added'] ?? [];

        /** @var array<int, array{id: int, name: string, full_name: string}> $repositoriesRemoved */
        $repositoriesRemoved = $payload['repositories_removed'] ?? [];

        return new InstallationRepositoriesWebhookPayload(
            action: $action,
            installationId: $installation['id'],
            repositoriesAdded: $repositoriesAdded,
            repositoriesRemoved: $repositoriesRemoved,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePullRequestPayload(array $payload): PullRequestWebhookPayload
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

        return new PullRequestWebhookPayload(
            action: $action,
            installationId: $installation['id'],
            repositoryId: $repository['id'],
            repositoryFullName: $repository['full_name'],
            pullRequestNumber: $pullRequest['number'],
            pullRequestTitle: $pullRequest['title'],
            pullRequestBody: $pullRequest['body'],
            baseBranch: $pullRequest['base']['ref'],
            headBranch: $pullRequest['head']['ref'],
            headSha: $pullRequest['head']['sha'],
            senderLogin: $sender['login'],
            author: new GitHubUser(
                login: $pullRequest['user']['login'],
                avatarUrl: $pullRequest['user']['avatar_url'] ?? null,
            ),
            isDraft: $pullRequest['draft'] ?? false,
            assignees: array_map(GitHubUser::fromArray(...), $pullRequest['assignees'] ?? []),
            reviewers: array_map(GitHubUser::fromArray(...), $pullRequest['requested_reviewers'] ?? []),
            labels: array_map(GitHubLabel::fromArray(...), $pullRequest['labels'] ?? []),
        );
    }

    /**
     * Extract installation ID from a GitHub webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractInstallationId(array $payload): ?int
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
    public function extractAction(array $payload): ?string
    {
        /** @var string|null $action */
        $action = $payload['action'] ?? null;

        return $action;
    }
}
