<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\GenerateBriefingContent;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Queue\Queue;
use App\Events\Briefings\BriefingGenerationFailed;
use App\Models\BriefingGeneration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessBriefingGeneration implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum execution time in seconds for processing generation content.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BriefingGeneration $generation,
    ) {
        $this->timeout = (int) config('briefings.limits.generation_timeout_seconds', 300);
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateBriefingContent $generateBriefingContent): void
    {
        try {
            $generateBriefingContent->handle($this->generation);
        } catch (Throwable $throwable) {
            $this->handleFailure($throwable);

            throw $throwable;
        }
    }

    /**
     * Handle a job failure callback.
     */
    public function failed(Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    /**
     * Persist failure state and emit failure events for the generation.
     */
    private function handleFailure(Throwable $exception): void
    {
        $this->generation->update([
            'status' => BriefingGenerationStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);

        BriefingGenerationFailed::dispatch($this->generation);

        Log::error('Briefing generation failed', [
            'generation_id' => $this->generation->id,
            'briefing_id' => $this->generation->briefing_id,
            'workspace_id' => $this->generation->workspace_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
