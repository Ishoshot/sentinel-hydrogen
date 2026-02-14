<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Actions\CodeIndexing\GenerateEmbeddingsForCodeIndexes;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateCodeEmbeddingsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int>  $codeIndexIds
     */
    public function __construct(
        public Repository $repository,
        public array $codeIndexIds,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateEmbeddingsForCodeIndexes $generateEmbeddingsForCodeIndexes): void
    {
        $generateEmbeddingsForCodeIndexes->handle($this->repository, $this->codeIndexIds);
    }
}
