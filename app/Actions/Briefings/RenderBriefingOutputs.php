<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingGeneration;
use App\Services\Briefings\BriefingOutputRenderer;
use App\Services\Briefings\ValueObjects\BriefingOutputFormats;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final readonly class RenderBriefingOutputs
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private BriefingOutputRenderer $outputRenderer = new BriefingOutputRenderer,
    ) {}

    /**
     * Render and persist configured output formats for a completed briefing generation.
     */
    public function handle(BriefingGeneration $generation): void
    {
        $generation->loadMissing('briefing');

        $disk = $this->outputRenderer->storageDisk();
        $outputPaths = [];
        $requestedFormats = $this->resolveOutputFormats($generation);

        if ($requestedFormats->includes(BriefingOutputFormat::Html)) {
            $htmlPath = $this->storeOutput($disk, $generation, 'briefing.html', $this->outputRenderer->renderHtml($generation));
            $outputPaths[BriefingOutputFormat::Html->value] = $htmlPath;
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Pdf)) {
            $pdfDriver = config('briefings.pdf.driver');

            if ($pdfDriver !== null) {
                $pdfPath = $this->storeOutput($disk, $generation, 'briefing.pdf', $this->outputRenderer->renderPdf($generation));
                $outputPaths[BriefingOutputFormat::Pdf->value] = $pdfPath;
            } else {
                Log::warning('Briefing PDF generation skipped - driver not configured', [
                    'generation_id' => $generation->id,
                ]);
            }
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Markdown)) {
            $markdownPath = $this->storeOutput($disk, $generation, 'briefing.md', $this->outputRenderer->renderMarkdown($generation));
            $outputPaths[BriefingOutputFormat::Markdown->value] = $markdownPath;
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Slides)) {
            $slidesPath = $this->storeOutput($disk, $generation, 'briefing.slides.json', $this->outputRenderer->renderSlides($generation));
            $outputPaths[BriefingOutputFormat::Slides->value] = $slidesPath;
        }

        $generation->update([
            'output_paths' => $outputPaths,
        ]);

        Log::info('Briefing rendered', [
            'generation_id' => $generation->id,
            'formats' => array_keys($outputPaths),
        ]);
    }

    /**
     * Persist a generated briefing artifact and return its storage path.
     */
    private function storeOutput(
        string $disk,
        BriefingGeneration $generation,
        string $filename,
        string $contents,
    ): string {
        $path = $this->outputRenderer->resolveStoragePath($generation, $filename);
        Storage::disk($disk)->put($path, $contents);

        return $path;
    }

    /**
     * Resolve output format configuration for the given generation.
     */
    private function resolveOutputFormats(BriefingGeneration $generation): BriefingOutputFormats
    {
        return $this->outputRenderer->resolveFormats($generation);
    }
}
