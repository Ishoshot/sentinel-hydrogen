<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Enums\BriefingGenerationStatus;
use App\Enums\Queue;
use App\Events\Briefings\BriefingGenerationCompleted;
use App\Events\Briefings\BriefingGenerationFailed;
use App\Events\Briefings\BriefingGenerationProgress;
use App\Events\Briefings\BriefingGenerationStarted;
use App\Models\BriefingGeneration;
use App\Services\Briefings\Contracts\BriefingDataCollector;
use App\Services\Briefings\Contracts\BriefingNarrativeGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ProcessBriefingGeneration implements ShouldQueue
{
    use Queueable;

    /** @var int The number of times the job may be attempted */
    public int $tries = 3;

    /** @var int The number of seconds the job can run before timing out */
    public int $timeout = 300;

    /** @param BriefingGeneration $generation The generation to process */
    public function __construct(
        public BriefingGeneration $generation,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /** Execute the job. */
    public function handle(
        BriefingDataCollector $dataCollector,
        BriefingNarrativeGenerator $narrativeGenerator,
    ): void {
        try {
            $this->updateProgress(BriefingGenerationStatus::Processing, 0, 'Starting briefing generation...');
            BriefingGenerationStarted::dispatch($this->generation);

            $this->generation->loadMissing('briefing');
            $briefing = $this->generation->briefing;

            if ($briefing === null) {
                throw new RuntimeException('Briefing template not found');
            }

            $this->updateProgress(BriefingGenerationStatus::Processing, 20, 'Collecting data...');
            $structuredData = $dataCollector->collect(
                $this->generation->workspace_id,
                $briefing->slug,
                $this->generation->parameters ?? [],
            );

            $this->updateProgress(BriefingGenerationStatus::Processing, 40, 'Detecting achievements...');
            $achievements = $dataCollector->detectAchievements($structuredData);

            $narrative = null;

            if ($briefing->requires_ai && $briefing->prompt_path !== null) {
                $this->updateProgress(BriefingGenerationStatus::Processing, 60, 'Generating narrative...');
                $narrative = $narrativeGenerator->generate(
                    $briefing->prompt_path,
                    $structuredData,
                    $achievements,
                );
            }

            $this->updateProgress(BriefingGenerationStatus::Processing, 80, 'Generating excerpts...');
            $excerpts = $narrativeGenerator->generateExcerpts($narrative ?? '', $structuredData);

            $this->generation->update([
                'status' => BriefingGenerationStatus::Completed,
                'progress' => 100,
                'progress_message' => 'Completed',
                'narrative' => $narrative,
                'structured_data' => $structuredData,
                'achievements' => $achievements,
                'excerpts' => $excerpts,
                'completed_at' => now(),
            ]);

            BriefingGenerationCompleted::dispatch($this->generation);

            Log::info('Briefing generation completed', [
                'generation_id' => $this->generation->id,
                'briefing_id' => $this->generation->briefing_id,
                'workspace_id' => $this->generation->workspace_id,
            ]);
        } catch (Throwable $throwable) {
            $this->handleFailure($throwable);

            throw $throwable;
        }
    }

    /** Handle a job failure. */
    public function failed(Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    /** Update the generation progress. */
    private function updateProgress(
        BriefingGenerationStatus $status,
        int $progress,
        string $message,
    ): void {
        $this->generation->update([
            'status' => $status,
            'progress' => $progress,
            'progress_message' => $message,
            'started_at' => $status === BriefingGenerationStatus::Processing && $this->generation->started_at === null
                ? now()
                : $this->generation->started_at,
        ]);

        BriefingGenerationProgress::dispatch($this->generation, $progress, $message);
    }

    /** Handle generation failure. */
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
