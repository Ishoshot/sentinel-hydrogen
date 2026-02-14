<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\Queue\Queue;
use App\Jobs\Reviews\ExecuteReviewRun as ExecuteReviewRunJob;
use App\Models\Run;
use App\Services\Queue\QueueResolver;
use App\Services\Queue\ValueObjects\JobContext;

final readonly class DispatchReviewRun
{
    /**
     * Create a new action instance.
     */
    public function __construct(private QueueResolver $queueResolver) {}

    /**
     * Dispatch a queued review run to the tier-appropriate queue.
     */
    public function handle(Run $run): Queue
    {
        $run->loadMissing('workspace');

        $workspace = $run->workspace;

        $queue = $workspace !== null
            ? $this->queueResolver->resolve(JobContext::forWorkspace(ExecuteReviewRunJob::class, $workspace, true, 'high'))->queue
            : Queue::ReviewsDefault;

        ExecuteReviewRunJob::dispatch($run->id, $queue);

        return $queue;
    }
}
