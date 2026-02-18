<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Actions\CodeIndexing\HandlePullRequestIndexCleanup;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessPullRequestIndexCleanup implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new cleanup job instance.
     */
    public function __construct(
        public Repository $repository,
        public int $pullRequestNumber,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(HandlePullRequestIndexCleanup $handlePullRequestIndexCleanup): void
    {
        $handlePullRequestIndexCleanup->handle($this->repository, $this->pullRequestNumber);
    }
}
