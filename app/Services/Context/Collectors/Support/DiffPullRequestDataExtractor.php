<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Support\MetadataExtractor;

/**
 * Extracts pull request data from run metadata into a structured array.
 */
final readonly class DiffPullRequestDataExtractor
{
    /**
     * Extract PR metadata from run metadata.
     *
     * @return array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    public function extract(MetadataExtractor $metadata, string $fullName): array
    {
        return [
            'number' => $metadata->int('pull_request_number'),
            'title' => $metadata->string('pull_request_title'),
            'body' => $metadata->stringOrNull('pull_request_body'),
            'base_branch' => $metadata->string('base_branch', 'main'),
            'head_branch' => $metadata->string('head_branch'),
            'head_sha' => $metadata->string('head_sha'),
            'sender_login' => $metadata->string('sender_login'),
            'repository_full_name' => $fullName,
            'author' => $metadata->author(),
            'is_draft' => $metadata->bool('is_draft'),
            'assignees' => $metadata->users('assignees'),
            'reviewers' => $metadata->users('reviewers'),
            'labels' => $metadata->labels(),
        ];
    }
}
