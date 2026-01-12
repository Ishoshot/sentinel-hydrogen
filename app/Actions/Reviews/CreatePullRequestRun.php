<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\RunStatus;
use App\Models\Repository;
use App\Models\Run;

/**
 * Creates a Run record for a pull request webhook event.
 */
final readonly class CreatePullRequestRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Create a run for a pull request webhook event.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     * @param  int|null  $greetingCommentId  The GitHub comment ID for the greeting comment
     * @param  string|null  $skipReason  If provided, creates a Skipped run with this reason
     */
    public function handle(Repository $repository, array $payload, ?int $greetingCommentId = null, ?string $skipReason = null): Run
    {
        $externalReference = sprintf(
            'github:pull_request:%s:%s',
            $payload['pull_request_number'],
            $payload['head_sha']
        );

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

        if ($skipReason !== null) {
            $metadata['skip_reason'] = $skipReason;
            $metadata['skip_message'] = $skipReason;
        }

        $status = $skipReason !== null ? RunStatus::Skipped : RunStatus::Queued;

        $run = Run::query()->firstOrCreate(
            [
                'workspace_id' => $repository->workspace_id,
                'repository_id' => $repository->id,
                'external_reference' => $externalReference,
            ],
            [
                'status' => $status,
                'started_at' => now(),
                'completed_at' => $skipReason !== null ? now() : null,
                'metadata' => $metadata,
                'created_at' => now(),
            ]
        );

        if ($run->wasRecentlyCreated) {
            $this->logRunCreated($repository, $run, $payload, $skipReason);
        }

        return $run;
    }

    /**
     * Log activity for a newly created run.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    private function logRunCreated(Repository $repository, Run $run, array $payload, ?string $skipReason = null): void
    {
        $repository->loadMissing('workspace');
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return;
        }

        $description = $skipReason !== null
            ? sprintf('Review skipped for PR #%d in %s: %s', $payload['pull_request_number'], $payload['repository_full_name'], $skipReason)
            : sprintf('Review queued for PR #%d in %s', $payload['pull_request_number'], $payload['repository_full_name']);

        $activityMetadata = [
            'pull_request_number' => $payload['pull_request_number'],
            'pull_request_title' => $payload['pull_request_title'],
            'head_sha' => $payload['head_sha'],
            'sender' => $payload['sender_login'],
            'action' => $payload['action'],
        ];

        if ($skipReason !== null) {
            $activityMetadata['skip_reason'] = $skipReason;
        }

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RunCreated,
            description: $description,
            subject: $run,
            metadata: $activityMetadata,
        );
    }
}
