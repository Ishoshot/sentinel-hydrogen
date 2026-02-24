<?php

declare(strict_types=1);

namespace App\Actions\CodeIndexing;

use App\Enums\Queue\Queue;
use App\Jobs\CodeIndexing\ProcessPullRequestPreIndex;
use App\Models\Repository;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
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
     * @param  array<string, mixed>  $logContext
     */
    public function handle(Repository $repository, PullRequestWebhookPayload $payload, array $logContext = []): void
    {
        if (! (bool) config('reviews.pr_preindex.enabled', false)) {
            return;
        }

        $eligibleActions = config('reviews.pr_preindex.eligible_actions', ['opened', 'synchronize', 'reopened']);
        if (! is_array($eligibleActions) || ! in_array($payload->action, $eligibleActions, true)) {
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
            'pr_number' => $payload->pullRequestNumber,
            'head_sha' => $payload->headSha,
            'mode' => $mode,
        ]));
    }
}
