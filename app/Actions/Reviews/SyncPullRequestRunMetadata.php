<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Repository;
use App\Models\Run;
use Illuminate\Support\Facades\Log;

/**
 * Syncs PR metadata (labels, assignees, reviewers, draft status) on existing runs.
 */
final readonly class SyncPullRequestRunMetadata
{
    /**
     * Sync metadata for an existing pull request run.
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    public function handle(Repository $repository, array $payload): ?Run
    {
        // Find the most recent run for this PR
        $run = $this->findLatestRunForPullRequest($repository, $payload['pull_request_number']);

        if (! $run instanceof Run) {
            Log::info('No existing run found for metadata sync', [
                'repository' => $payload['repository_full_name'],
                'pr_number' => $payload['pull_request_number'],
            ]);

            return null;
        }

        $this->updateMetadata($run, $payload);

        Log::info('Pull request metadata synced', [
            'run_id' => $run->id,
            'repository' => $payload['repository_full_name'],
            'pr_number' => $payload['pull_request_number'],
            'action' => $payload['action'],
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
     *
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    private function updateMetadata(Run $run, array $payload): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $run->metadata ?? [];

        // Update mutable metadata fields
        $metadata['pull_request_title'] = $payload['pull_request_title'];
        $metadata['pull_request_body'] = $payload['pull_request_body'];
        $metadata['author'] = $payload['author'];
        $metadata['is_draft'] = $payload['is_draft'];
        $metadata['assignees'] = $payload['assignees'];
        $metadata['reviewers'] = $payload['reviewers'];
        $metadata['labels'] = $payload['labels'];
        $metadata['last_synced_at'] = now()->toISOString();
        $metadata['last_sync_action'] = $payload['action'];

        $run->metadata = $metadata;
        $run->save();
    }
}
