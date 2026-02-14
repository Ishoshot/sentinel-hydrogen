<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\GitHub\Contracts\PostsSkipReasonComment;
use App\Actions\Reviews\Support\PullRequestRunActivityLogger;
use App\Actions\Reviews\Support\PullRequestRunMetadataBuilder;
use App\Actions\Reviews\Support\PullRequestRunSkipResolver;
use App\Actions\Reviews\Support\PullRequestRunUserResolver;
use App\Enums\Reviews\RunStatus;
use App\Enums\Reviews\SkipReason;
use App\Models\Repository;
use App\Models\Run;
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
    ) {}

    /**
     * Create a run for a pull request webhook event.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     * @param  int|null  $greetingCommentId  The GitHub comment ID for the greeting comment
     * @param  string|null  $skipReason  If provided, creates a Skipped run with this reason
     */
    public function handle(Repository $repository, array $payload, ?int $greetingCommentId = null, ?string $skipReason = null): Run
    {
        $skipResolution = $this->skipResolver->resolve($repository, $skipReason);

        $run = DB::transaction(function () use ($repository, $payload, $greetingCommentId, $skipResolution): Run {
            $externalReference = sprintf(
                'github:pull_request:%s:%s',
                $payload['pull_request_number'],
                $payload['head_sha']
            );

            $metadata = $this->metadataBuilder->build($payload, $greetingCommentId, $skipResolution);

            $status = $skipResolution->shouldSkip() ? RunStatus::Skipped : RunStatus::Queued;
            $initiatedByUserId = $this->userResolver->resolveUserId($payload['author']['login']);

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
                    'pr_number' => $payload['pull_request_number'],
                    'pr_title' => $payload['pull_request_title'],
                    'base_branch' => $payload['base_branch'],
                    'head_branch' => $payload['head_branch'],
                    'metadata' => $metadata,
                    'created_at' => now(),
                ]
            );
        });

        if ($run->wasRecentlyCreated) {
            $this->activityLogger->logCreated($repository, $run, $payload, $skipResolution->skipReason);
        }

        if ($skipResolution->workspace === null) {
            $this->postSkipReasonComment->handle($run, SkipReason::OrphanedRepository);
        } elseif ($skipResolution->shouldPostPlanLimitComment()) {
            $this->postSkipReasonComment->handle($run, SkipReason::PlanLimitReached, $skipResolution->skipReason);
        }

        return $run;
    }
}
