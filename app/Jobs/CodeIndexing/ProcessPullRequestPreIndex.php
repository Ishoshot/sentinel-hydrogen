<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Actions\CodeIndexing\HandlePullRequestPreIndex;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessPullRequestPreIndex implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new class instance.
     */
    public function __construct(
        public Repository $repository,
        public PullRequestWebhookPayload $payload,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(HandlePullRequestPreIndex $handlePullRequestPreIndex): void
    {
        $handlePullRequestPreIndex->handle($this->repository, $this->payload);
    }
}
