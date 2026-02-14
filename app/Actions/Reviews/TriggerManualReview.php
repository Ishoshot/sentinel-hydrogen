<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\Reviews\ManualPullRequestPayloadFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Triggers a manual code review for a pull request.
 *
 * This action is called when a user comments @sentinel review on a PR,
 * and simulates the same review flow that would occur from a webhook.
 */
final readonly class TriggerManualReview
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private CreatePullRequestRun $createPullRequestRun,
        private DispatchReviewRun $dispatchReviewRun,
        private ManualPullRequestPayloadFactory $payloadFactory,
    ) {}

    /**
     * Trigger a manual review for a pull request.
     *
     * @return array{success: bool, run: Run|null, message: string}
     */
    public function handle(
        Repository $repository,
        int $prNumber,
        string $senderLogin,
    ): array {
        $installation = $repository->installation;

        if ($installation === null) {
            return [
                'success' => false,
                'run' => null,
                'message' => 'Repository installation not found.',
            ];
        }

        $ctx = [
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
            'sender' => $senderLogin,
        ];

        Log::info('Triggering manual review', $ctx);

        // Check if auto-review is enabled
        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Manual review requested but auto-review disabled', $ctx);

            return [
                'success' => false,
                'run' => null,
                'message' => 'Code reviews are disabled for this repository. Enable auto-review in repository settings to use this feature.',
            ];
        }

        // Fetch PR data from GitHub API
        try {
            $prData = $this->githubApi->getPullRequest(
                installationId: $installation->installation_id,
                owner: $repository->owner,
                repo: $repository->name,
                number: $prNumber
            );
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch PR data for manual review', array_merge($ctx, [
                'error' => $throwable->getMessage(),
            ]));

            return [
                'success' => false,
                'run' => null,
                'message' => 'Unable to fetch pull request details from GitHub.',
            ];
        }

        // Transform GitHub API response to webhook payload format
        $payload = $this->payloadFactory->make($repository, $installation->installation_id, $prData, $senderLogin);

        // Post acknowledgment comment
        $greetingCommentId = $this->postAcknowledgmentComment(
            $installation->installation_id,
            $repository->owner,
            $repository->name,
            $prNumber
        );

        // Create the run using the existing action
        $run = $this->createPullRequestRun->handle($repository, $payload, $greetingCommentId);

        // Check if run was skipped (e.g., due to plan limits)
        if ($run->status === RunStatus::Skipped) {
            Log::info('Manual review skipped', array_merge($ctx, [
                'run_id' => $run->id,
                'reason' => $run->metadata['skip_reason'] ?? 'unknown',
            ]));

            return [
                'success' => false,
                'run' => $run,
                'message' => (string) ($run->metadata['skip_reason'] ?? 'Review was skipped.'),
            ];
        }

        // Dispatch the review job to the tier-appropriate queue
        $queue = $this->dispatchReviewRun->handle($run);

        Log::info('Manual review queued', array_merge($ctx, [
            'run_id' => $run->id,
            'queue' => $queue->value,
        ]));

        return [
            'success' => true,
            'run' => $run,
            'message' => "Review started. I'll analyze the changes and post my findings shortly.",
        ];
    }

    /**
     * Post an acknowledgment comment to the PR.
     */
    private function postAcknowledgmentComment(int $installationId, string $owner, string $repo, int $prNumber): ?int
    {
        try {
            $comment = $this->githubApi->createIssueComment(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                number: $prNumber,
                body: $this->getAcknowledgmentMessage()
            );

            return (int) ($comment['id'] ?? 0) ?: null;
        } catch (Throwable $throwable) {
            Log::warning('Failed to post acknowledgment comment', [
                'owner' => $owner,
                'repo' => $repo,
                'pr_number' => $prNumber,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the acknowledgment message for manual review.
     */
    private function getAcknowledgmentMessage(): string
    {
        return "**Sentinel**: Starting code review...\n\nI'll analyze the changes in this pull request and post my findings shortly.";
    }
}
