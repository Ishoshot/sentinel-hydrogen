<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Actions\Reviews\PostRunAnnotations as PostRunAnnotationsAction;
use App\Enums\Queue;
use App\Enums\RunStatus;
use App\Models\Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to post review annotations to GitHub.
 *
 * This job is dispatched after a review run completes successfully.
 * It posts findings as PR review comments on GitHub.
 */
final class PostRunAnnotations implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $runId)
    {
        $this->onQueue(Queue::Annotations->value);
    }

    /**
     * Execute the job.
     */
    public function handle(PostRunAnnotationsAction $postAnnotations): void
    {
        $run = Run::query()->find($this->runId);

        if ($run === null) {
            Log::warning('Run not found for annotation posting', ['run_id' => $this->runId]);

            return;
        }

        if ($run->status !== RunStatus::Completed) {
            Log::info('Skipping annotations for non-completed run', [
                'run_id' => $this->runId,
                'status' => $run->status->value,
            ]);

            return;
        }

        if ($run->findings()->whereHas('annotations')->exists()) {
            Log::info('Annotations already posted for run', ['run_id' => $this->runId]);

            return;
        }

        $count = $postAnnotations->handle($run);

        Log::info('Posted run annotations', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'annotations_count' => $count,
        ]);
    }
}
