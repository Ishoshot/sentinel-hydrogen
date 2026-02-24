<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Builders;

use App\Actions\Reviews\ValueObjects\PullRequestRunSkipResolution;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;

final readonly class PullRequestRunMetadataBuilder
{
    /**
     * Build run metadata for pull request run creation.
     *
     * @return array<string, mixed>
     */
    public function build(PullRequestWebhookPayload $payload, ?int $greetingCommentId, PullRequestRunSkipResolution $skipResolution): array
    {
        $metadata = [
            'provider' => 'github',
            'repository_full_name' => $payload->repositoryFullName,
            'pull_request_number' => $payload->pullRequestNumber,
            'pull_request_title' => $payload->pullRequestTitle,
            'pull_request_body' => $payload->pullRequestBody,
            'base_branch' => $payload->baseBranch,
            'head_branch' => $payload->headBranch,
            'head_sha' => $payload->headSha,
            'sender_login' => $payload->senderLogin,
            'action' => $payload->action,
            'installation_id' => $payload->installationId,
            'author' => $payload->author->toArray(),
            'is_draft' => $payload->isDraft,
            'assignees' => array_map(fn (GitHubUser $user): array => $user->toArray(), $payload->assignees),
            'reviewers' => array_map(fn (GitHubUser $user): array => $user->toArray(), $payload->reviewers),
            'labels' => array_map(fn (GitHubLabel $label): array => $label->toArray(), $payload->labels),
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
