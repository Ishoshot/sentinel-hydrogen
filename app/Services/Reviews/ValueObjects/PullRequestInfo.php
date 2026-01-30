<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Core pull request information.
 */
final readonly class PullRequestInfo
{
    /**
     * Create a new PullRequestInfo instance.
     *
     * @param  array<int, GitHubUser>  $assignees
     * @param  array<int, GitHubUser>  $reviewers
     * @param  array<int, GitHubLabel>  $labels
     */
    public function __construct(
        public int $number,
        public string $title,
        public ?string $body,
        public string $baseBranch,
        public string $headBranch,
        public string $headSha,
        public string $senderLogin,
        public string $repositoryFullName,
        public GitHubUser $author,
        public bool $isDraft,
        public array $assignees = [],
        public array $reviewers = [],
        public array $labels = [],
    ) {}

    /**
     * Create from array.
     *
     * @param  array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            number: $data['number'],
            title: $data['title'],
            body: $data['body'],
            baseBranch: $data['base_branch'],
            headBranch: $data['head_branch'],
            headSha: $data['head_sha'],
            senderLogin: $data['sender_login'],
            repositoryFullName: $data['repository_full_name'],
            author: GitHubUser::fromArray($data['author']),
            isDraft: $data['is_draft'],
            assignees: array_map(GitHubUser::fromArray(...), $data['assignees']),
            reviewers: array_map(GitHubUser::fromArray(...), $data['reviewers']),
            labels: array_map(GitHubLabel::fromArray(...), $data['labels']),
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'body' => $this->body,
            'base_branch' => $this->baseBranch,
            'head_branch' => $this->headBranch,
            'head_sha' => $this->headSha,
            'sender_login' => $this->senderLogin,
            'repository_full_name' => $this->repositoryFullName,
            'author' => $this->author->toArray(),
            'is_draft' => $this->isDraft,
            'assignees' => array_map(fn (GitHubUser $u): array => $u->toArray(), $this->assignees),
            'reviewers' => array_map(fn (GitHubUser $u): array => $u->toArray(), $this->reviewers),
            'labels' => array_map(fn (GitHubLabel $l): array => $l->toArray(), $this->labels),
        ];
    }
}
