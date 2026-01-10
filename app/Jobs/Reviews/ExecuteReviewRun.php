<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Actions\Reviews\ExecuteReviewRun as ExecuteReviewRunAction;
use App\Models\Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ExecuteReviewRun implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $runId)
    {
        $this->onQueue('reviews-default');
    }

    /**
     * Execute the job.
     */
    public function handle(ExecuteReviewRunAction $executeReviewRun): void
    {
        $run = Run::query()->find($this->runId);

        if ($run === null) {
            Log::warning('Review run not found for execution', ['run_id' => $this->runId]);

            return;
        }

        $executeReviewRun->handle($run);

        Log::info('Review run executed', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'status' => $run->status->value,
        ]);
    }
}
