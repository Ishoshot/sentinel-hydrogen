<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Loggers;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Repository;
use App\Models\Run;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;

final readonly class PullRequestRunActivityLogger
{
    /**
     * Create a new pull request run activity logger.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Record activity details for a newly created pull request run.
     */
    public function logCreated(Repository $repository, Run $run, PullRequestWebhookPayload $payload, ?string $skipReason): void
    {
        $repository->loadMissing('workspace');
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return;
        }

        $description = $skipReason !== null
            ? sprintf('Review skipped for PR #%d in %s: %s', $payload->pullRequestNumber, $payload->repositoryFullName, $skipReason)
            : sprintf('Review queued for PR #%d in %s', $payload->pullRequestNumber, $payload->repositoryFullName);

        $metadata = [
            'pull_request_number' => $payload->pullRequestNumber,
            'pull_request_title' => $payload->pullRequestTitle,
            'head_sha' => $payload->headSha,
            'sender' => $payload->senderLogin,
            'action' => $payload->action,
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
