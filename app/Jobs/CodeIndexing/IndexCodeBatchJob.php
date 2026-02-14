<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Actions\CodeIndexing\IndexRepositoryBatch;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class IndexCodeBatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     */
    public function __construct(
        public Repository $repository,
        public string $commitSha,
        public array $files,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(IndexRepositoryBatch $indexRepositoryBatch): void
    {
        $indexRepositoryBatch->handle($this->repository, $this->commitSha, $this->files);
    }
}
