<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\GitHub\HandlePushWebhook;
use App\Enums\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Process push webhooks to sync Sentinel config when relevant files change.
 */
final class ProcessPushWebhook implements ShouldQueue
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
    public function handle(HandlePushWebhook $handlePushWebhook): void
    {
        $handlePushWebhook->handle($this->payload);
    }
}
