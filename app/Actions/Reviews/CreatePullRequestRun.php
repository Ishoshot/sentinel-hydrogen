<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\Builders\PullRequestRunMetadataBuilder;
use App\Actions\Reviews\Loggers\PullRequestRunActivityLogger;
use App\Actions\Reviews\Resolvers\PullRequestRunSkipResolver;
use App\Actions\Reviews\Resolvers\PullRequestRunUserResolver;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Models\Repository;
use App\Models\Run;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Run record for a pull request webhook event.
 */
final readonly class CreatePullRequestRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PullRequestRunSkipResolver $skipResolver,
        private PullRequestRunMetadataBuilder $metadataBuilder,
        private PullRequestRunUserResolver $userResolver,
        private PullRequestRunActivityLogger $activityLogger,
        private PostsSkipReasonComment $postSkipReasonComment,
        private UpdateRunAcknowledgmentComment $updateRunAcknowledgmentComment,
    ) {}

    /**
     * Create a run for a pull request webhook event.
     *
     * @param  int|null  $greetingCommentId  The GitHub comment ID for the greeting comment
     * @param  string|null  $skipReason  If provided, creates a Skipped run with this reason
     */
    public function handle(Repository $repository, PullRequestWebhookPayload $payload, ?int $greetingCommentId = null, ?string $skipReason = null): Run
    {
        $skipResolution = $this->skipResolver->resolve($repository, $skipReason);

        $run = DB::transaction(function () use ($repository, $payload, $greetingCommentId, $skipResolution): Run {
            $externalReference = sprintf(
                'github:pull_request:%s:%s',
                $payload->pullRequestNumber,
                $payload->headSha
            );

            $metadata = $this->metadataBuilder->build($payload, $greetingCommentId, $skipResolution);

            $status = $skipResolution->shouldSkip() ? RunStatus::Skipped : RunStatus::Queued;
            $initiatedByUserId = $this->userResolver->resolveUserId($payload->author->login);

            return Run::query()->firstOrCreate(
                [
                    'workspace_id' => $repository->workspace_id,
                    'repository_id' => $repository->id,
                    'external_reference' => $externalReference,
                ],
                [
                    'status' => $status,
                    'started_at' => now(),
                    'completed_at' => $skipResolution->shouldSkip() ? now() : null,
                    'initiated_by_id' => $initiatedByUserId,
                    'pr_number' => $payload->pullRequestNumber,
                    'pr_title' => $payload->pullRequestTitle,
                    'base_branch' => $payload->baseBranch,
                    'head_branch' => $payload->headBranch,
                    'metadata' => $metadata,
                    'created_at' => now(),
                ]
            );
        });

        if ($run->wasRecentlyCreated) {
            $this->activityLogger->logCreated($repository, $run, $payload, $skipResolution->skipReason);
        }

        $acknowledgmentUpdated = false;

        if ($skipResolution->shouldSkip() && is_string($skipResolution->skipReason)) {
            $acknowledgmentUpdated = $this->updateRunAcknowledgmentComment->markSkipped($run, $skipResolution->skipReason);
        }

        if ($acknowledgmentUpdated) {
            return $run;
        }

        if (! $skipResolution->workspace instanceof \App\Models\Workspace) {
            $this->postSkipReasonComment->handle($run, SkipReason::OrphanedRepository);
        } elseif ($skipResolution->shouldPostPlanLimitComment()) {
            $this->postSkipReasonComment->handle($run, SkipReason::PlanLimitReached, $skipResolution->skipReason);
        }

        return $run;
    }
}
