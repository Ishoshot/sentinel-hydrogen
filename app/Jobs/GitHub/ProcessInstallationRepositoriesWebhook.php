<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\GitHub\HandleInstallationRepositoriesWebhook;
use App\Enums\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessInstallationRepositoriesWebhook implements ShouldQueue
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
    public function handle(HandleInstallationRepositoriesWebhook $handleInstallationRepositoriesWebhook): void
    {
        $handleInstallationRepositoriesWebhook->handle($this->payload);
    }
}
