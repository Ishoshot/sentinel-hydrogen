<?php

declare(strict_types=1);

namespace App\Actions\Briefings\Handlers;

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Events\Briefings\BriefingGenerationProgress;
use App\Models\BriefingGeneration;

/**
 * Persists generation progress and broadcasts progress updates.
 */
final readonly class BriefingGenerationProgressHandler
{
    /**
     * Update the generation progress and broadcast the event.
     */
    public function update(
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
