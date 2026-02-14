<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Events\Briefings\BriefingGenerationCompleted;
use App\Events\Briefings\BriefingGenerationProgress;
use App\Events\Briefings\BriefingGenerationStarted;
use App\Jobs\Briefings\RenderBriefingPdf;
use App\Models\BriefingGeneration;
use App\Services\Briefings\BriefingProviderKeyResolver;
use App\Services\Briefings\Contracts\BriefingDataCollector;
use App\Services\Briefings\Contracts\BriefingNarrativeGenerator;
use App\Services\Briefings\Contracts\BriefingSlidesBuilder;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GenerateBriefingContent
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private BriefingDataCollector $dataCollector,
        private BriefingNarrativeGenerator $narrativeGenerator,
        private BriefingSlidesBuilder $slidesBuilder,
        private BriefingProviderKeyResolver $providerKeyResolver,
    ) {}

    /**
     * Generate briefing content from data collection through narrative and slide creation.
     */
    public function handle(BriefingGeneration $generation): void
    {
        $this->updateProgress($generation, BriefingGenerationStatus::Processing, 0, 'Starting briefing generation...');
        BriefingGenerationStarted::dispatch($generation);

        $generation->loadMissing(['briefing', 'workspace']);
        $briefing = $generation->briefing;
        $workspace = $generation->workspace;

        if ($briefing === null) {
            throw new RuntimeException('Briefing template not found');
        }

        if ($workspace === null) {
            throw new RuntimeException('Workspace not found');
        }

        $aiConfig = $this->providerKeyResolver->resolveConfiguration($workspace);

        $this->updateProgress($generation, BriefingGenerationStatus::Processing, 20, 'Collecting data...');

        $parameters = BriefingParameters::fromArray($generation->parameters ?? []);
        $structuredData = $this->dataCollector->collect($generation->workspace_id, $briefing->slug, $parameters);

        $this->updateProgress($generation, BriefingGenerationStatus::Processing, 40, 'Detecting achievements...');
        $achievements = $this->dataCollector->detectAchievements($structuredData);

        $narrative = null;
        $metadata = $generation->metadata ?? [];
        $metadata['byok'] = $aiConfig->isByok;

        if ($briefing->requires_ai && $briefing->prompt_path !== null) {
            $this->updateProgress($generation, BriefingGenerationStatus::Processing, 60, 'Generating narrative...');

            $narrativeResult = $this->narrativeGenerator->generate(
                $briefing->prompt_path,
                $structuredData,
                $achievements,
                $aiConfig,
            );

            $narrative = $narrativeResult->text;
            $metadata['ai_telemetry'] = $narrativeResult->telemetry->toArray();
        }

        $this->updateProgress($generation, BriefingGenerationStatus::Processing, 80, 'Generating excerpts...');
        $excerpts = $this->narrativeGenerator->generateExcerpts($narrative ?? '', $structuredData);

        $this->updateProgress($generation, BriefingGenerationStatus::Processing, 85, 'Building slide deck...');
        $slides = $this->slidesBuilder->build($briefing, $structuredData, $achievements, $narrative);

        $structuredPayload = $structuredData->toArray();
        $structuredPayload['slides'] = $slides->toArray();

        $this->updateProgress($generation, BriefingGenerationStatus::Processing, 90, 'Rendering outputs...');

        $generation->update([
            'status' => BriefingGenerationStatus::Completed,
            'progress' => 100,
            'progress_message' => 'Completed',
            'narrative' => $narrative,
            'structured_data' => $structuredPayload,
            'achievements' => $achievements->toArray(),
            'excerpts' => $excerpts->toArray(),
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);

        RenderBriefingPdf::dispatch($generation);
        BriefingGenerationCompleted::dispatch($generation);

        Log::info('Briefing generation completed', [
            'generation_id' => $generation->id,
            'briefing_id' => $generation->briefing_id,
            'workspace_id' => $generation->workspace_id,
        ]);
    }

    /**
     * Persist generation progress and broadcast progress updates.
     */
    private function updateProgress(
        BriefingGeneration $generation,
        BriefingGenerationStatus $status,
        int $progress,
        string $message,
    ): void {
        $generation->update([
            'status' => $status,
            'progress' => $progress,
            'progress_message' => $message,
            'started_at' => $status === BriefingGenerationStatus::Processing && $generation->started_at === null
                ? now()
                : $generation->started_at,
        ]);

        BriefingGenerationProgress::dispatch($generation, $progress, $message);
    }
}
