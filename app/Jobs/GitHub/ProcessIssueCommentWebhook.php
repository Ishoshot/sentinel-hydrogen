<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\Commands\HandleIssueCommentCommand;
use App\Enums\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes issue_comment webhooks from GitHub.
 *
 * Handles @sentinel mentions in issue and PR comments.
 */
final class ProcessIssueCommentWebhook implements ShouldQueue
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
    public function handle(HandleIssueCommentCommand $handleIssueCommentCommand): void
    {
        $handleIssueCommentCommand->handle($this->payload);
    }
}
