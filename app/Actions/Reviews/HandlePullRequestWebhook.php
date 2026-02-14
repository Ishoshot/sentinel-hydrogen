<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\Reviews\Checkers\PullRequestWebhookPreflightChecker;
use App\Actions\Reviews\Resolvers\PullRequestWebhookRepositoryResolver;
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
        private PostsGreetingComment $postGreeting,
        private PostsConfigErrorComment $postConfigError,
        private PostsAutoReviewDisabledComment $postAutoReviewDisabled,
        private DispatchReviewRun $dispatchReviewRun,
        private PullRequestWebhookRepositoryResolver $repositoryResolver,
        private PullRequestWebhookPreflightChecker $preflightChecker,
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
            $data['installation_id'],
            $data['repository_full_name'],
            $data['action']
        );
        $webhookCtx['pr_number'] = $data['pull_request_number'];

        Log::info('Processing pull request webhook', $webhookCtx);

        $shouldTriggerReview = $this->webhookService->shouldTriggerReview($data['action']);
        $shouldSyncMetadata = $this->webhookService->shouldSyncMetadata($data['action']);

        if (! $shouldTriggerReview && ! $shouldSyncMetadata) {
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
        $ctx['pr_number'] = $data['pull_request_number'];

        $preflight = $this->preflightChecker->evaluate($repository, $data, $ctx);
        if ($preflight->shouldPostAutoReviewDisabledComment) {
            $this->postAutoReviewDisabled->handle($repository, $data['pull_request_number']);

            return;
        }

        if ($preflight->configErrorMessage !== null) {
            $this->postConfigError->handle(
                $repository,
                $data['pull_request_number'],
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

        $greetingCommentId = $this->postGreeting->handle($repository, $data['pull_request_number']);

        $run = $this->createPullRequestRun->handle($repository, $data, $greetingCommentId);

        $queue = $this->dispatchReviewRun->handle($run);

        Log::info('Pull request queued for review', array_merge($ctx, [
            'run_id' => $run->id,
            'pr_title' => $data['pull_request_title'],
            'head_sha' => $data['head_sha'],
            'queue' => $queue->value,
            'greeting_comment_id' => $greetingCommentId,
        ]));
    }
}
