<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Reviews\Support\ManualReviewAcknowledgmentCommentPoster;
use App\Actions\Reviews\Support\ManualReviewEligibilityChecker;
use App\Actions\Reviews\Support\ManualReviewPullRequestFetcher;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Reviews\ManualPullRequestPayloadFactory;
use Illuminate\Support\Facades\Log;

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
        private ManualReviewEligibilityChecker $eligibilityChecker,
        private ManualReviewPullRequestFetcher $pullRequestFetcher,
        private ManualReviewAcknowledgmentCommentPoster $acknowledgmentCommentPoster,
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
        $ctx = [
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
            'sender' => $senderLogin,
        ];

        Log::info('Triggering manual review', $ctx);

        $eligibility = $this->eligibilityChecker->check($repository, $ctx);
        if (! $eligibility->allowed) {
            return [
                'success' => false,
                'run' => null,
                'message' => $eligibility->message ?? 'Repository installation not found.',
            ];
        }

        $installation = $eligibility->installation;
        if (! $installation instanceof \App\Models\Installation) {
            return [
                'success' => false,
                'run' => null,
                'message' => 'Repository installation not found.',
            ];
        }

        $pullRequest = $this->pullRequestFetcher->fetch(
            installationId: $installation->installation_id,
            owner: $repository->owner,
            repo: $repository->name,
            pullRequestNumber: $prNumber,
            context: $ctx,
        );
        if (! $pullRequest->successful) {
            return [
                'success' => false,
                'run' => null,
                'message' => $pullRequest->message ?? 'Unable to fetch pull request details from GitHub.',
            ];
        }

        // Transform GitHub API response to webhook payload format
        $payload = $this->payloadFactory->make(
            $repository,
            $installation->installation_id,
            $pullRequest->pullRequestData,
            $senderLogin
        );

        // Post acknowledgment comment
        $greetingCommentId = $this->acknowledgmentCommentPoster->post(
            $installation->installation_id,
            $repository->owner,
            $repository->name,
            $prNumber,
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
}
