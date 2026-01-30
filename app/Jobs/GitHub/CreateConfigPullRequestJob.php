<?php

declare(strict_types=1);

namespace App\Jobs\GitHub;

use App\Actions\GitHub\CreateConfigPullRequest;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class CreateConfigPullRequestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $repositoryId
    ) {
        $this->onQueue(Queue::Sync->value);
    }

    /**
     * Execute the job.
     */
    public function handle(CreateConfigPullRequest $action): void
    {
        $repository = Repository::with('installation')->find($this->repositoryId);

        if ($repository === null) {
            Log::warning('CreateConfigPullRequestJob: Repository not found', [
                'repository_id' => $this->repositoryId,
            ]);

            return;
        }

        $result = $action->handle($repository);

        Log::info('CreateConfigPullRequestJob completed', [
            'repository_id' => $this->repositoryId,
            'repository' => $repository->full_name,
            'result' => $result->toArray(),
        ]);
    }
}
