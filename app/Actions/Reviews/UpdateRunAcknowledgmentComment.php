<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Run;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\Reviews\Builders\RunAcknowledgmentStatusBodyBuilder;
use App\Support\RepositoryNameParser;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class UpdateRunAcknowledgmentComment
{
    /**
     * Create a new acknowledgment comment updater instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private RunAcknowledgmentStatusBodyBuilder $statusBodyBuilder,
    ) {}

    /**
     * Mark an existing acknowledgment comment as completed.
     */
    public function markCompleted(Run $run, int $findingsCount): bool
    {
        return $this->sync($run, $this->statusBodyBuilder->forCompleted($run, $findingsCount));
    }

    /**
     * Mark an existing acknowledgment comment as skipped.
     */
    public function markSkipped(Run $run, string $reason): bool
    {
        return $this->sync($run, $this->statusBodyBuilder->forSkipped($run, $reason));
    }

    /**
     * Mark an existing acknowledgment comment as failed.
     */
    public function markFailed(Run $run, string $errorType): bool
    {
        return $this->sync($run, $this->statusBodyBuilder->forFailed($run, $errorType));
    }

    /**
     * Update the GitHub acknowledgment comment for the given run.
     */
    private function sync(Run $run, string $body): bool
    {
        if (! config('reviews.ack_comment_updates', false)) {
            return false;
        }

        $context = $this->resolveContext($run);
        if ($context === null) {
            return false;
        }

        $commentId = $context['comment_id'];
        if ($commentId === null) {
            return false;
        }

        try {
            $this->gitHubApiService->updateIssueComment(
                installationId: $context['installation_id'],
                owner: $context['owner'],
                repo: $context['repo'],
                commentId: $commentId,
                body: $body
            );

            return true;
        } catch (Throwable $throwable) {
            Log::warning('Failed to update run acknowledgment comment', [
                'run_id' => $run->id,
                'comment_id' => $commentId,
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve repository and pull request details required to update an acknowledgment comment.
     *
     * @return array{installation_id: int, owner: string, repo: string, pull_request_number: int, comment_id: int|null}|null
     */
    private function resolveContext(Run $run): ?array
    {
        $run->loadMissing(['repository.installation', 'workspace']);

        $repository = $run->repository;
        $installation = $repository?->installation;

        if ($repository === null || $installation === null) {
            return null;
        }

        $pullRequestNumber = $run->getEffectivePrNumber();
        if ($pullRequestNumber === null) {
            return null;
        }

        $parsed = RepositoryNameParser::parse($repository->full_name);
        if ($parsed === null) {
            return null;
        }

        /** @var array<string, mixed> $metadata */
        $metadata = $run->metadata ?? [];
        $commentId = isset($metadata['github_comment_id']) && is_numeric($metadata['github_comment_id'])
            ? (int) $metadata['github_comment_id']
            : null;

        return [
            'installation_id' => $installation->installation_id,
            'owner' => $parsed['owner'],
            'repo' => $parsed['repo'],
            'pull_request_number' => $pullRequestNumber,
            'comment_id' => $commentId,
        ];
    }
}
