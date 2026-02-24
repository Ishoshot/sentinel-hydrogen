<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Repository;
use App\Models\Run;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use App\Services\Reviews\ValueObjects\GitHubLabel;
use App\Services\Reviews\ValueObjects\GitHubUser;
use Illuminate\Support\Facades\Log;

/**
 * Syncs PR metadata (labels, assignees, reviewers, draft status) on existing runs.
 */
final readonly class SyncPullRequestRunMetadata
{
    /**
     * Sync metadata for an existing pull request run.
     */
    public function handle(Repository $repository, PullRequestWebhookPayload $payload): ?Run
    {
        // Find the most recent run for this PR
        $run = $this->findLatestRunForPullRequest($repository, $payload->pullRequestNumber);

        if (! $run instanceof Run) {
            Log::info('No existing run found for metadata sync', [
                'repository' => $payload->repositoryFullName,
                'pr_number' => $payload->pullRequestNumber,
            ]);

            return null;
        }

        $this->updateMetadata($run, $payload);

        Log::info('Pull request metadata synced', [
            'run_id' => $run->id,
            'repository' => $payload->repositoryFullName,
            'pr_number' => $payload->pullRequestNumber,
            'action' => $payload->action,
        ]);

        return $run;
    }

    /**
     * Find the latest run for a pull request.
     */
    private function findLatestRunForPullRequest(Repository $repository, int $prNumber): ?Run
    {
        return Run::query()
            ->where('repository_id', $repository->id)
            ->where('external_reference', 'like', sprintf('github:pull_request:%d:%%', $prNumber))
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Update the run's metadata with new PR data.
     */
    private function updateMetadata(Run $run, PullRequestWebhookPayload $payload): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $run->metadata ?? [];

        // Update mutable metadata fields
        $metadata['pull_request_title'] = $payload->pullRequestTitle;
        $metadata['pull_request_body'] = $payload->pullRequestBody;
        $metadata['author'] = $payload->author->toArray();
        $metadata['is_draft'] = $payload->isDraft;
        $metadata['assignees'] = array_map(fn (GitHubUser $user): array => $user->toArray(), $payload->assignees);
        $metadata['reviewers'] = array_map(fn (GitHubUser $user): array => $user->toArray(), $payload->reviewers);
        $metadata['labels'] = array_map(fn (GitHubLabel $label): array => $label->toArray(), $payload->labels);
        $metadata['last_synced_at'] = now()->toISOString();
        $metadata['last_sync_action'] = $payload->action;

        $run->metadata = $metadata;
        $run->save();
    }
}
