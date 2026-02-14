<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Repository;
use App\Models\Run;

final readonly class PullRequestRunActivityLogger
{
    /**
     * Create a new pull request run activity logger.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Record activity details for a newly created pull request run.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    public function logCreated(Repository $repository, Run $run, array $payload, ?string $skipReason): void
    {
        $repository->loadMissing('workspace');
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return;
        }

        $description = $skipReason !== null
            ? sprintf('Review skipped for PR #%d in %s: %s', $payload['pull_request_number'], $payload['repository_full_name'], $skipReason)
            : sprintf('Review queued for PR #%d in %s', $payload['pull_request_number'], $payload['repository_full_name']);

        $metadata = [
            'pull_request_number' => $payload['pull_request_number'],
            'pull_request_title' => $payload['pull_request_title'],
            'head_sha' => $payload['head_sha'],
            'sender' => $payload['sender_login'],
            'action' => $payload['action'],
        ];

        if ($skipReason !== null) {
            $metadata['skip_reason'] = $skipReason;
        }

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::RunCreated,
            description: $description,
            subject: $run,
            metadata: $metadata,
        );
    }
}
