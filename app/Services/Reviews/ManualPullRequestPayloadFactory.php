<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Repository;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;

final class ManualPullRequestPayloadFactory
{
    /**
     * Build the payload expected by CreatePullRequestRun.
     *
     * @param  array<string, mixed>  $pullRequestData
     */
    public function make(Repository $repository, int $installationId, array $pullRequestData, string $senderLogin): PullRequestWebhookPayload
    {
        return new PullRequestWebhookPayload(
            action: 'manual_trigger',
            installationId: $installationId,
            repositoryId: $repository->github_id,
            repositoryFullName: $repository->full_name,
            pullRequestNumber: (int) ($pullRequestData['number'] ?? 0),
            pullRequestTitle: (string) ($pullRequestData['title'] ?? ''),
            pullRequestBody: isset($pullRequestData['body']) && is_string($pullRequestData['body']) ? $pullRequestData['body'] : null,
            baseBranch: (string) ($pullRequestData['base']['ref'] ?? ''),
            headBranch: (string) ($pullRequestData['head']['ref'] ?? ''),
            headSha: (string) ($pullRequestData['head']['sha'] ?? ''),
            senderLogin: $senderLogin,
            author: new GitHubUser(
                login: (string) ($pullRequestData['user']['login'] ?? ''),
                avatarUrl: isset($pullRequestData['user']['avatar_url']) && is_string($pullRequestData['user']['avatar_url']) ? $pullRequestData['user']['avatar_url'] : null,
            ),
            isDraft: (bool) ($pullRequestData['draft'] ?? false),
            assignees: $this->extractUsers($pullRequestData['assignees'] ?? []),
            reviewers: $this->extractUsers($pullRequestData['requested_reviewers'] ?? []),
            labels: $this->extractLabels($pullRequestData['labels'] ?? []),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, GitHubUser>
     */
    private function extractUsers(array $users): array
    {
        return array_map(fn (array $user): GitHubUser => new GitHubUser(
            login: (string) ($user['login'] ?? ''),
            avatarUrl: isset($user['avatar_url']) && is_string($user['avatar_url']) ? $user['avatar_url'] : null,
        ), $users);
    }

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @return array<int, GitHubLabel>
     */
    private function extractLabels(array $labels): array
    {
        return array_map(fn (array $label): GitHubLabel => new GitHubLabel(
            name: (string) ($label['name'] ?? ''),
            color: (string) ($label['color'] ?? ''),
        ), $labels);
    }
}
