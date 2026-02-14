<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Models\Installation;
use App\Models\Repository;
use App\Services\GitHub\GitHubWebhookService;
use App\Services\Logging\LogContext;
use App\Services\SentinelConfig\TriggerRuleEvaluator;
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
        private TriggerRuleEvaluator $triggerEvaluator,
        private DispatchReviewRun $dispatchReviewRun,
        private ResolvePullRequestSentinelConfig $resolvePullRequestSentinelConfig,
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

        $installation = Installation::query()->where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            Log::warning('Installation not found for pull request webhook', $webhookCtx);

            return;
        }

        $repository = Repository::query()
            ->where('installation_id', $installation->id)
            ->where('github_id', $data['repository_id'])
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for pull request webhook', array_merge($webhookCtx, [
                'github_repository_id' => $data['repository_id'],
            ]));

            return;
        }

        if ($shouldSyncMetadata) {
            $this->syncMetadata->handle($repository, $data);

            return;
        }

        $ctx = LogContext::fromRepository($repository);
        $ctx['pr_number'] = $data['pull_request_number'];

        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Auto-review disabled for repository', $ctx);

            $this->postAutoReviewDisabled->handle($repository, $data['pull_request_number']);

            return;
        }

        $repository->loadMissing('settings');
        $settings = $repository->settings;

        if ($settings !== null && $settings->hasConfigError()) {
            Log::warning('Repository has config error, skipping review', array_merge($ctx, [
                'config_error' => $settings->config_error,
            ]));

            $this->postConfigError->handle(
                $repository,
                $data['pull_request_number'],
                $settings->config_error ?? 'Unknown configuration error'
            );

            $skipReason = sprintf('Configuration error: %s', $settings->config_error ?? 'Unknown error');
            $this->createPullRequestRun->handle($repository, $data, null, $skipReason);

            return;
        }

        $sentinelConfig = $this->resolvePullRequestSentinelConfig->handle(
            $repository,
            $data['head_branch'],
            $data['base_branch']
        );

        $triggersConfig = $sentinelConfig->getTriggersOrDefault();

        $labelNames = array_map(
            fn (array $label): string => $label['name'],
            $data['labels']
        );

        $triggerResult = $this->triggerEvaluator->evaluate($triggersConfig, [
            'base_branch' => $data['base_branch'],
            'head_branch' => $data['head_branch'],
            'author_login' => $data['author']['login'],
            'labels' => $labelNames,
        ]);

        if (! $triggerResult['should_trigger']) {
            Log::info('Review skipped due to trigger rules', array_merge($ctx, [
                'reason' => $triggerResult['reason'],
            ]));

            $this->createPullRequestRun->handle($repository, $data, null, $triggerResult['reason']);

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
