<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Actions\CodeIndexing\HandlePullRequestPreIndex;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessPullRequestPreIndex implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}  $payload
     */
    public function __construct(
        public Repository $repository,
        public array $payload,
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
