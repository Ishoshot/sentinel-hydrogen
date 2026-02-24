<?php

declare(strict_types=1);

namespace App\Services\GitHub\ValueObjects;

use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;

final readonly class PullRequestWebhookPayload
{
    /**
     * @param  array<int, GitHubUser>  $assignees
     * @param  array<int, GitHubUser>  $reviewers
     * @param  array<int, GitHubLabel>  $labels
     */
    public function __construct(
        public string $action,
        public int $installationId,
        public int $repositoryId,
        public string $repositoryFullName,
        public int $pullRequestNumber,
        public string $pullRequestTitle,
        public ?string $pullRequestBody,
        public string $baseBranch,
        public string $headBranch,
        public string $headSha,
        public string $senderLogin,
        public GitHubUser $author,
        public bool $isDraft,
        public array $assignees,
        public array $reviewers,
        public array $labels,
    ) {}

    /**
     * Create an instance from a parsed webhook payload array.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            action: $payload['action'],
            installationId: $payload['installation_id'],
            repositoryId: $payload['repository_id'],
            repositoryFullName: $payload['repository_full_name'],
            pullRequestNumber: $payload['pull_request_number'],
            pullRequestTitle: $payload['pull_request_title'],
            pullRequestBody: $payload['pull_request_body'],
            baseBranch: $payload['base_branch'],
            headBranch: $payload['head_branch'],
            headSha: $payload['head_sha'],
            senderLogin: $payload['sender_login'],
            author: GitHubUser::fromArray($payload['author']),
            isDraft: $payload['is_draft'],
            assignees: array_map(GitHubUser::fromArray(...), $payload['assignees']),
            reviewers: array_map(GitHubUser::fromArray(...), $payload['reviewers']),
            labels: array_map(GitHubLabel::fromArray(...), $payload['labels']),
        );
    }

    /**
     * Convert the payload back to an array representation.
     *
     * @return array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'installation_id' => $this->installationId,
            'repository_id' => $this->repositoryId,
            'repository_full_name' => $this->repositoryFullName,
            'pull_request_number' => $this->pullRequestNumber,
            'pull_request_title' => $this->pullRequestTitle,
            'pull_request_body' => $this->pullRequestBody,
            'base_branch' => $this->baseBranch,
            'head_branch' => $this->headBranch,
            'head_sha' => $this->headSha,
            'sender_login' => $this->senderLogin,
            'author' => $this->author->toArray(),
            'is_draft' => $this->isDraft,
            'assignees' => array_map(fn (GitHubUser $user): array => $user->toArray(), $this->assignees),
            'reviewers' => array_map(fn (GitHubUser $user): array => $user->toArray(), $this->reviewers),
            'labels' => array_map(fn (GitHubLabel $label): array => $label->toArray(), $this->labels),
        ];
    }
}
