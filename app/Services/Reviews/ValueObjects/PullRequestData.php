<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Complete pull request data resolved from GitHub for code reviews.
 */
final readonly class PullRequestData
{
    /**
     * Create a new PullRequestData instance.
     *
     * @param  array<int, PullRequestFile>  $files
     */
    public function __construct(
        public PullRequestInfo $pullRequest,
        public array $files,
        public PullRequestMetrics $metrics,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pullRequest: PullRequestInfo::fromArray($data['pull_request']),
            files: array_map(PullRequestFile::fromArray(...), $data['files']),
            metrics: PullRequestMetrics::fromArray($data['metrics']),
        );
    }

    /**
     * Get the pull request number.
     */
    public function prNumber(): int
    {
        return $this->pullRequest->number;
    }

    /**
     * Check if this is a draft PR.
     */
    public function isDraft(): bool
    {
        return $this->pullRequest->isDraft;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}
     */
    public function toArray(): array
    {
        return [
            'pull_request' => $this->pullRequest->toArray(),
            'files' => array_map(fn (PullRequestFile $f): array => $f->toArray(), $this->files),
            'metrics' => $this->metrics->toArray(),
        ];
    }
}
