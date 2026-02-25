<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\CodeIndexing\DispatchPullRequestPreIndex;
use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\Reviews\Guards\PullRequestWebhookPreflightGuard;
use App\Actions\Reviews\Resolvers\PullRequestWebhookRepositoryResolver;
use App\Jobs\CodeIndexing\ProcessPullRequestIndexCleanup;
use App\Services\GitHub\GitHubWebhookService;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;

/**
 * Handles pull request webhook processing and review run orchestration.
 */
final readonly class HandlePullRequestWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubWebhookService $webhookService,
        private CreatePullRequestRun $createPullRequestRun,
        private SyncPullRequestRunMetadata $syncMetadata,
        private SupersedeActiveRuns $supersedeActiveRuns,
        private PostsGreetingComment $postGreeting,
        private PostsConfigErrorComment $postConfigError,
        private PostsAutoReviewDisabledComment $postAutoReviewDisabled,
        private DispatchReviewRun $dispatchReviewRun,
        private DispatchPullRequestPreIndex $dispatchPullRequestPreIndex,
        private PullRequestWebhookRepositoryResolver $repositoryResolver,
        private PullRequestWebhookPreflightGuard $preflightGuard,
    ) {}

    /**
     * Handle the pull request webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $data = $this->webhookService->parsePullRequestPayload($payload);

        $webhookCtx = LogContext::forWebhook(
            $data->installationId,
            $data->repositoryFullName,
            $data->action
        );
        $webhookCtx['pr_number'] = $data->pullRequestNumber;

        Log::info('Processing pull request webhook', $webhookCtx);

        $shouldTriggerReview = $this->webhookService->shouldTriggerReview($data->action);
        $shouldSyncMetadata = $this->webhookService->shouldSyncMetadata($data->action);
        $shouldCleanupPreIndex = $this->webhookService->shouldCleanupPreIndex($data->action);

        if (! $shouldTriggerReview && ! $shouldSyncMetadata && ! $shouldCleanupPreIndex) {
            Log::info('Ignoring pull request action', $webhookCtx);

            return;
        }

        $repository = $this->repositoryResolver->resolve($data, $webhookCtx);
        if (! $repository instanceof \App\Models\Repository) {
            return;
        }

        if ($shouldSyncMetadata) {
            $this->syncMetadata->handle($repository, $data);

            return;
        }

        $ctx = LogContext::fromRepository($repository);
        $ctx['pr_number'] = $data->pullRequestNumber;

        if ($shouldCleanupPreIndex) {
            ProcessPullRequestIndexCleanup::dispatch($repository, $data->pullRequestNumber);

            Log::info('Queued pull request pre-index cleanup', $ctx);

            return;
        }

        $preflight = $this->preflightGuard->evaluate($repository, $data, $ctx);
        if ($preflight->shouldPostAutoReviewDisabledComment) {
            $this->postAutoReviewDisabled->handle($repository, $data->pullRequestNumber);

            return;
        }

        if ($preflight->configErrorMessage !== null) {
            $this->postConfigError->handle(
                $repository,
                $data->pullRequestNumber,
                $preflight->configErrorMessage
            );

            $skipReason = sprintf('Configuration error: %s', $preflight->configErrorMessage);
            $this->createPullRequestRun->handle($repository, $data, null, $skipReason);

            return;
        }

        if ($preflight->skipReason !== null) {
            $this->createPullRequestRun->handle($repository, $data, null, $preflight->skipReason);

            return;
        }

        $this->dispatchPullRequestPreIndex->handle($repository, $data, $ctx);

        $this->supersedeActiveRuns->handle($repository, $data->pullRequestNumber);

        $greetingCommentId = $this->postGreeting->handle($repository, $data->pullRequestNumber);

        $run = $this->createPullRequestRun->handle($repository, $data, $greetingCommentId);

        $queue = $this->dispatchReviewRun->handle($run);

        Log::info('Pull request queued for review', array_merge($ctx, [
            'run_id' => $run->id,
            'pr_title' => $data->pullRequestTitle,
            'head_sha' => $data->headSha,
            'queue' => $queue->value,
            'greeting_comment_id' => $greetingCommentId,
        ]));
    }
}
