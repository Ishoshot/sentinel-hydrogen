<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Builders;

use App\Actions\Reviews\ValueObjects\PullRequestRunSkipResolution;

final readonly class PullRequestRunMetadataBuilder
{
    /**
     * Build run metadata for pull request run creation.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     * @return array<string, mixed>
     */
    public function build(array $payload, ?int $greetingCommentId, PullRequestRunSkipResolution $skipResolution): array
    {
        $metadata = [
            'provider' => 'github',
            'repository_full_name' => $payload['repository_full_name'],
            'pull_request_number' => $payload['pull_request_number'],
            'pull_request_title' => $payload['pull_request_title'],
            'pull_request_body' => $payload['pull_request_body'],
            'base_branch' => $payload['base_branch'],
            'head_branch' => $payload['head_branch'],
            'head_sha' => $payload['head_sha'],
            'sender_login' => $payload['sender_login'],
            'action' => $payload['action'],
            'installation_id' => $payload['installation_id'],
            'author' => $payload['author'],
            'is_draft' => $payload['is_draft'],
            'assignees' => $payload['assignees'],
            'reviewers' => $payload['reviewers'],
            'labels' => $payload['labels'],
        ];

        if ($greetingCommentId !== null) {
            $metadata['github_comment_id'] = $greetingCommentId;
        }

        if ($skipResolution->skipReason !== null) {
            $metadata['skip_reason'] = $skipResolution->skipReason;
            $metadata['skip_message'] = $skipResolution->skipReason;
        }

        if ($skipResolution->planLimitTriggered) {
            $metadata['skip_reason_code'] = $skipResolution->skipReasonCode ?? 'plan_limit';
        }

        return $metadata;
    }
}
