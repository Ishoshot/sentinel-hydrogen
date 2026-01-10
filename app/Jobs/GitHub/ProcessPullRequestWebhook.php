<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\Reviews\CreatePullRequestRun;
use App\Actions\Reviews\SyncPullRequestRunMetadata;
use App\Enums\Queue;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Installation;
use App\Models\Repository;
use App\Services\GitHub\GitHubWebhookService;
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
        SyncPullRequestRunMetadata $syncMetadata
    ): void {
        $data = $webhookService->parsePullRequestPayload($this->payload);

        Log::info('Processing pull request webhook', [
            'action' => $data['action'],
            'repository' => $data['repository_full_name'],
            'pr_number' => $data['pull_request_number'],
        ]);

        $shouldTriggerReview = $webhookService->shouldTriggerReview($data['action']);
        $shouldSyncMetadata = $webhookService->shouldSyncMetadata($data['action']);

        // Ignore actions that don't trigger review or metadata sync
        if (! $shouldTriggerReview && ! $shouldSyncMetadata) {
            Log::info('Ignoring pull request action', ['action' => $data['action']]);

            return;
        }

        $installation = Installation::where('installation_id', $data['installation_id'])->first();

        if ($installation === null) {
            Log::warning('Installation not found for pull request webhook', [
                'installation_id' => $data['installation_id'],
            ]);

            return;
        }

        $repository = Repository::where('installation_id', $installation->id)
            ->where('github_id', $data['repository_id'])
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for pull request webhook', [
                'repository_id' => $data['repository_id'],
                'full_name' => $data['repository_full_name'],
            ]);

            return;
        }

        // Handle metadata sync (labels, assignees, reviewers, draft status)
        if ($shouldSyncMetadata) {
            $syncMetadata->handle($repository, $data);

            return;
        }

        // Handle new review creation
        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Auto-review disabled for repository', [
                'repository' => $data['repository_full_name'],
            ]);

            return;
        }

        $run = $createPullRequestRun->handle($repository, $data);

        // Determine the queue based on workspace tier
        $workspace = $repository->workspace;
        $queue = $workspace !== null
            ? Queue::reviewQueueForTier($workspace->getCurrentTier())
            : Queue::ReviewsDefault;

        ExecuteReviewRun::dispatch($run->id, $queue);

        Log::info('Pull request queued for review', [
            'run_id' => $run->id,
            'repository' => $data['repository_full_name'],
            'pr_number' => $data['pull_request_number'],
            'pr_title' => $data['pull_request_title'],
            'head_sha' => $data['head_sha'],
            'action' => $data['action'],
            'queue' => $queue->value,
        ]);
    }
}
