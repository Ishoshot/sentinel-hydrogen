<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

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
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GitHubWebhookService $webhookService): void
    {
        $data = $webhookService->parsePullRequestPayload($this->payload);

        Log::info('Processing pull request webhook', [
            'action' => $data['action'],
            'repository' => $data['repository_full_name'],
            'pr_number' => $data['pull_request_number'],
        ]);

        // Only process actions that should trigger a review
        if (! $webhookService->shouldTriggerReview($data['action'])) {
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

        // Check if auto-review is enabled
        if (! $repository->hasAutoReviewEnabled()) {
            Log::info('Auto-review disabled for repository', [
                'repository' => $data['repository_full_name'],
            ]);

            return;
        }

        // TODO: In Phase 3, dispatch the actual code review job
        // For now, just log that we would trigger a review
        Log::info('Pull request ready for review', [
            'repository' => $data['repository_full_name'],
            'pr_number' => $data['pull_request_number'],
            'pr_title' => $data['pull_request_title'],
            'head_sha' => $data['head_sha'],
            'action' => $data['action'],
        ]);

        // Placeholder for Phase 3: AI Code Review Pipeline
        // dispatch(new TriggerCodeReview($repository, $data));
    }
}
