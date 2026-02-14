<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Repository;

final class ManualPullRequestPayloadFactory
{
    /**
     * Build the payload format expected by CreatePullRequestRun.
     *
     * @param  array<string, mixed>  $pullRequestData
     * @return array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function make(Repository $repository, int $installationId, array $pullRequestData, string $senderLogin): array
    {
        return [
            'action' => 'manual_trigger',
            'installation_id' => $installationId,
            'repository_id' => $repository->github_id,
            'repository_full_name' => $repository->full_name,
            'pull_request_number' => (int) ($pullRequestData['number'] ?? 0),
            'pull_request_title' => (string) ($pullRequestData['title'] ?? ''),
            'pull_request_body' => isset($pullRequestData['body']) && is_string($pullRequestData['body']) ? $pullRequestData['body'] : null,
            'base_branch' => (string) ($pullRequestData['base']['ref'] ?? ''),
            'head_branch' => (string) ($pullRequestData['head']['ref'] ?? ''),
            'head_sha' => (string) ($pullRequestData['head']['sha'] ?? ''),
            'sender_login' => $senderLogin,
            'author' => [
                'login' => (string) ($pullRequestData['user']['login'] ?? ''),
                'avatar_url' => isset($pullRequestData['user']['avatar_url']) && is_string($pullRequestData['user']['avatar_url']) ? $pullRequestData['user']['avatar_url'] : null,
            ],
            'is_draft' => (bool) ($pullRequestData['draft'] ?? false),
            'assignees' => $this->extractUsers($pullRequestData['assignees'] ?? []),
            'reviewers' => $this->extractUsers($pullRequestData['requested_reviewers'] ?? []),
            'labels' => $this->extractLabels($pullRequestData['labels'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, array{login: string, avatar_url: string|null}>
     */
    private function extractUsers(array $users): array
    {
        return array_map(fn (array $user): array => [
            'login' => (string) ($user['login'] ?? ''),
            'avatar_url' => isset($user['avatar_url']) && is_string($user['avatar_url']) ? $user['avatar_url'] : null,
        ], $users);
    }

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @return array<int, array{name: string, color: string}>
     */
    private function extractLabels(array $labels): array
    {
        return array_map(fn (array $label): array => [
            'name' => (string) ($label['name'] ?? ''),
            'color' => (string) ($label['color'] ?? ''),
        ], $labels);
    }
}
