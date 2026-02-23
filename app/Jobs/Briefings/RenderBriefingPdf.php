<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\RenderBriefingOutputs;
use App\Enums\Queue\Queue;
use App\Models\BriefingGeneration;
use App\Services\Briefings\BriefingOutputRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class RenderBriefingPdf implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BriefingGeneration $generation,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(RenderBriefingOutputs $renderBriefingOutputs): void
    {
        $renderBriefingOutputs->handle($this->generation);
    }

    /**
     * Handle a job failure by cleaning up any partially stored output files.
     */
    public function failed(Throwable $exception): void
    {
        $renderer = app(BriefingOutputRenderer::class);
        $disk = $renderer->storageDisk();
        $basePath = $renderer->resolveStoragePath($this->generation, '');

        $storage = Storage::disk($disk);
        $orphanedFiles = $storage->files(mb_rtrim($basePath, '/'));

        foreach ($orphanedFiles as $file) {
            $storage->delete($file);
        }

        Log::warning('Cleaned up partial briefing output files after render failure', [
            'generation_id' => $this->generation->id,
            'files_removed' => count($orphanedFiles),
            'error' => $exception->getMessage(),
        ]);
    }
}
