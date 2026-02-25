<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Reviews\Guards\ManualReviewEligibilityGuard;
use App\Actions\Reviews\Publishers\ManualReviewAcknowledgmentCommentPublisher;
use App\Actions\Reviews\Resolvers\ManualReviewPullRequestResolver;
use App\Actions\Reviews\ValueObjects\ManualReviewResult;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
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
        private ManualReviewEligibilityGuard $eligibilityGuard,
        private ManualReviewPullRequestResolver $pullRequestFetcher,
        private ManualReviewAcknowledgmentCommentPublisher $acknowledgmentCommentPublisher,
        private CreatePullRequestRun $createPullRequestRun,
        private DispatchReviewRun $dispatchReviewRun,
        private ManualPullRequestPayloadFactory $payloadFactory,
    ) {}

    /**
     * Trigger a manual review for a pull request.
     */
    public function handle(
        Repository $repository,
        int $prNumber,
        string $senderLogin,
    ): ManualReviewResult {
        $ctx = [
            'repository_id' => $repository->id,
            'pr_number' => $prNumber,
            'sender' => $senderLogin,
        ];

        Log::info('Triggering manual review', $ctx);

        $eligibility = $this->eligibilityGuard->check($repository, $ctx);
        if (! $eligibility->allowed) {
            return ManualReviewResult::failure(
                $eligibility->message ?? 'Repository installation not found.',
            );
        }

        $installation = $eligibility->installation;
        if (! $installation instanceof \App\Models\Installation) {
            return ManualReviewResult::failure('Repository installation not found.');
        }

        $pullRequest = $this->pullRequestFetcher->resolve(
            installationId: $installation->installation_id,
            owner: $repository->owner,
            repo: $repository->name,
            pullRequestNumber: $prNumber,
            context: $ctx,
        );
        if (! $pullRequest->successful) {
            return ManualReviewResult::failure(
                $pullRequest->message ?? 'Unable to fetch pull request details from GitHub.',
            );
        }

        // Transform GitHub API response to webhook payload format
        $payload = $this->payloadFactory->make(
            $repository,
            $installation->installation_id,
            $pullRequest->pullRequestData,
            $senderLogin
        );

        // Post acknowledgment comment
        $greetingCommentId = $this->acknowledgmentCommentPublisher->post(
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

            return ManualReviewResult::skipped(
                $run,
                (string) ($run->metadata['skip_reason'] ?? 'Review was skipped.'),
            );
        }

        // Dispatch the review job to the tier-appropriate queue
        $queue = $this->dispatchReviewRun->handle($run);

        Log::info('Manual review queued', array_merge($ctx, [
            'run_id' => $run->id,
            'queue' => $queue->value,
        ]));

        return ManualReviewResult::success(
            $run,
            "Review started. I'll analyze the changes and post my findings shortly.",
        );
    }
}
