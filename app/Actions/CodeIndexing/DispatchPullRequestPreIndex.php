<?php

declare(strict_types=1);

namespace App\Actions\CodeIndexing;

use App\Enums\Queue\Queue;
use App\Jobs\CodeIndexing\ProcessPullRequestPreIndex;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

final readonly class DispatchPullRequestPreIndex
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private HandlePullRequestPreIndex $handlePullRequestPreIndex,
    ) {}

    /**
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     * @param  array<string, mixed>  $logContext
     */
    public function handle(Repository $repository, array $payload, array $logContext = []): void
    {
        if (! (bool) config('reviews.pr_preindex.enabled', false)) {
            return;
        }

        $eligibleActions = config('reviews.pr_preindex.eligible_actions', ['opened', 'synchronize', 'reopened']);
        if (! is_array($eligibleActions) || ! in_array($payload['action'], $eligibleActions, true)) {
            return;
        }

        $mode = (string) config('reviews.pr_preindex.mode', 'async');

        if ($mode === 'blocking') {
            $this->handlePullRequestPreIndex->handle($repository, $payload);

            return;
        }

        ProcessPullRequestPreIndex::dispatch($repository, $payload)
            ->onQueue(Queue::CodeIndexing->value);

        Log::info('Queued pull request pre-index job', array_merge($logContext, [
            'repository_id' => $repository->id,
            'pr_number' => $payload['pull_request_number'],
            'head_sha' => $payload['head_sha'],
            'mode' => $mode,
        ]));
    }
}
