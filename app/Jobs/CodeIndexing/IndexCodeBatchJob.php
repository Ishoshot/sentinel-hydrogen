<?php

declare(strict_types=1);

namespace App\Jobs\CodeIndexing;

use App\Actions\CodeIndexing\IndexRepositoryBatch;
use App\Enums\Queue\Queue;
use App\Models\Repository;
use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class IndexCodeBatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     * @param  array{scope_type?: string, scope_ref?: string, pull_request_number?: int|null, head_sha?: string|null}|null  $scope
     */
    public function __construct(
        public Repository $repository,
        public string $commitSha,
        public array $files,
        public ?array $scope = null,
    ) {
        $this->onQueue(Queue::CodeIndexing->value);
    }

    /**
     * Execute the job.
     */
    public function handle(IndexRepositoryBatch $indexRepositoryBatch): void
    {
        $indexRepositoryBatch->handle(
            repository: $this->repository,
            commitSha: $this->commitSha,
            files: $this->files,
            scope: CodeIndexScope::fromArray($this->normalizeScope()),
        );
    }

    /**
     * @return array{scope_type?: string, scope_ref?: string, pull_request_number?: int|null, head_sha?: string|null}|null
     */
    private function normalizeScope(): ?array
    {
        if (! is_array($this->scope)) {
            return null;
        }

        $normalized = [];

        if (is_string($this->scope['scope_type'] ?? null)) {
            $normalized['scope_type'] = $this->scope['scope_type'];
        }

        if (is_string($this->scope['scope_ref'] ?? null)) {
            $normalized['scope_ref'] = $this->scope['scope_ref'];
        }

        if (array_key_exists('pull_request_number', $this->scope)) {
            $normalized['pull_request_number'] = is_int($this->scope['pull_request_number'])
                ? $this->scope['pull_request_number']
                : null;
        }

        if (array_key_exists('head_sha', $this->scope)) {
            $normalized['head_sha'] = is_string($this->scope['head_sha'])
                ? $this->scope['head_sha']
                : null;
        }

        return $normalized;
    }
}
