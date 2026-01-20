<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\GitHub\Contracts\PostsAutoReviewDisabledComment;
use App\Actions\GitHub\Contracts\PostsConfigErrorComment;
use App\Actions\GitHub\Contracts\PostsGreetingComment;
use App\Actions\Reviews\CreatePullRequestRun;
use App\Actions\Reviews\SyncPullRequestRunMetadata;
use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Enums\Queue;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Installation;
use App\Models\Repository;
use App\Services\GitHub\GitHubWebhookService;
use App\Services\Logging\LogContext;
use App\Services\Queue\JobContext;
use App\Services\Queue\QueueResolver;
use App\Services\SentinelConfig\Contracts\SentinelConfigParser;
use App\Services\SentinelConfig\TriggerRuleEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ProcessPullRequestWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload
    ) {
        $this->onQueue(Queue::Webhooks->value);
    }

    /**
     * Execute the job.
     */
    public function handle(
        GitHubWebhookService $webhookService,
        CreatePullRequestRun $createPullRequestRun,
        SyncPullRequestRunMetadata $syncMetadata,
        PostsGreetingComment $postGreeting,
        PostsConfigErrorComment $postConfigError,
        PostsAutoReviewDisabledComment $postAutoReviewDisabled,
        TriggerRuleEvaluator $triggerEvaluator,
        QueueResolver $queueResolver,
        FetchesSentinelConfig $fetchConfig,
        SentinelConfigParser $configParser
    ): void {
        $data = $webhookService->parsePullRequestPayload($this->payload);

        $webhookCtx = LogContext::forWebhook(
            $data['installation_id'],
            $data['repository_full_name'],
            $data['action']
        );
        $webhookCtx['pr_number'] = $data['pull_request_number'];

        Log::info('Processing pull request webhook', $webhookCtx);

        $shouldTriggerReview = $webhookService->shouldTriggerReview($data['action']);
        $shouldSyncMetadata = $webhookService->shouldSyncMetadata($data['action']);

        // Ignore actions that don't trigger review or metadata sync
        if (! $shouldTriggerReview && ! $shouldSyncMetadata) {
            Log::info('Ignoring pull request action', $webhookCtx);

            return;
        }

        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            Log::warning('Installation not found for pull request webhook', $webhookCtx);

            return;
        }

        $repository = Repository::where('installation_id', $installation->id)
            ->where('github_id', $data['repository_id'])
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for pull request webhook', array_merge($webhookCtx, [
                'github_repository_id' => $data['repository_id'],
            ]));

            return;
        }

        // Handle metadata sync (labels, assignees, reviewers, draft status, update (title, branch change))
        if ($shouldSyncMetadata) {
            $syncMetadata->handle($repository, $data);

            return;
        }

        $ctx = LogContext::fromRepository($repository);
        $ctx['pr_number'] = $data['pull_request_number'];

        // Handle new review creation
        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Auto-review disabled for repository', $ctx);

            // Post "review skipped" comment to PR (no run created to preserve quota)
            $postAutoReviewDisabled->handle($repository, $data['pull_request_number']);

            return;
        }

        // Check for config errors before proceeding with review
        $repository->loadMissing('settings');
        $settings = $repository->settings;

        if ($settings !== null && $settings->hasConfigError()) {
            Log::warning('Repository has config error, skipping review', array_merge($ctx, [
                'config_error' => $settings->config_error,
            ]));

            // Post error comment to PR
            $postConfigError->handle(
                $repository,
                $data['pull_request_number'],
                $settings->config_error ?? 'Unknown configuration error'
            );

            // Create a skipped run with error details
            $skipReason = sprintf('Configuration error: %s', $settings->config_error ?? 'Unknown error');
            $createPullRequestRun->handle($repository, $data, null, $skipReason);

            return;
        }

        // Evaluate trigger rules using branch-aware config (head → base → default)
        $sentinelConfig = $this->fetchConfigFromBranch(
            $repository,
            $data['head_branch'],
            $data['base_branch'],
            $fetchConfig,
            $configParser
        );
        $triggersConfig = $sentinelConfig->getTriggersOrDefault();

        $labelNames = array_map(
            fn (array $label): string => $label['name'],
            $data['labels']
        );

        $triggerResult = $triggerEvaluator->evaluate($triggersConfig, [
            'base_branch' => $data['base_branch'],
            'head_branch' => $data['head_branch'],
            'author_login' => $data['author']['login'],
            'labels' => $labelNames,
        ]);

        if (! $triggerResult['should_trigger']) {
            Log::info('Review skipped due to trigger rules', array_merge($ctx, [
                'reason' => $triggerResult['reason'],
            ]));

            // Create skipped run silently (no PR comment for trigger rules)
            $createPullRequestRun->handle($repository, $data, null, $triggerResult['reason']);

            return;
        }

        // Post greeting comment immediately for low latency feedback
        $greetingCommentId = $postGreeting->handle($repository, $data['pull_request_number']);

        $run = $createPullRequestRun->handle($repository, $data, $greetingCommentId);

        // Determine the queue based on workspace tier
        $workspace = $repository->workspace;
        $queue = $workspace !== null
            ? $queueResolver->resolve(JobContext::forWorkspace(ExecuteReviewRun::class, $workspace, true, 'high'))->queue
            : Queue::ReviewsDefault;

        ExecuteReviewRun::dispatch($run->id, $queue);

        Log::info('Pull request queued for review', array_merge($ctx, [
            'run_id' => $run->id,
            'pr_title' => $data['pull_request_title'],
            'head_sha' => $data['head_sha'],
            'queue' => $queue->value,
            'greeting_comment_id' => $greetingCommentId,
        ]));
    }

    /**
     * Fetch sentinel config with branch fallback: head → base → default.
     *
     * Returns default config if not found in any branch.
     */
    private function fetchConfigFromBranch(
        Repository $repository,
        string $headBranch,
        string $baseBranch,
        FetchesSentinelConfig $fetchConfig,
        SentinelConfigParser $configParser
    ): SentinelConfig {
        $defaultBranch = $repository->default_branch;

        // Build ordered list of branches to try (deduplicated)
        $branches = array_values(array_unique(array_filter([
            $headBranch,
            $baseBranch,
            $defaultBranch,
        ])));

        foreach ($branches as $branch) {
            $fetchResult = $fetchConfig->handle($repository, $branch);
            if (! $fetchResult['found']) {
                continue;
            }

            if ($fetchResult['content'] === null) {
                continue;
            }

            $parseResult = $configParser->tryParse($fetchResult['content']);

            if ($parseResult['success'] && $parseResult['config'] !== null) {
                Log::debug('ProcessPullRequestWebhook: Found sentinel config', [
                    'repository' => $repository->full_name,
                    'branch' => $branch,
                    'tried_branches' => $branches,
                ]);

                return $parseResult['config'];
            }
        }

        Log::debug('ProcessPullRequestWebhook: No sentinel config found, using defaults', [
            'repository' => $repository->full_name,
            'tried_branches' => $branches,
        ]);

        return SentinelConfig::default();
    }
}
