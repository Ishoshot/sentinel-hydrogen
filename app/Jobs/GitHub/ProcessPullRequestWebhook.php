<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\Reviews\HandlePullRequestWebhook;
use App\Enums\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
    public function handle(HandlePullRequestWebhook $handlePullRequestWebhook): void
    {
        $handlePullRequestWebhook->handle($this->payload);
    }
}
