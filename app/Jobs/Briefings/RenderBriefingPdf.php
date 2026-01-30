<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Enums\Briefings\BriefingOutputFormat;
use App\Enums\Queue\Queue;
use App\Models\BriefingGeneration;
use App\Services\Briefings\ValueObjects\BriefingOutputFormats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

final class RenderBriefingPdf implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  BriefingGeneration  $generation  The generation to render
     */
    public function __construct(
        public BriefingGeneration $generation,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generation->loadMissing('briefing');

        $disk = config('briefings.storage.disk', 'r2');
        $basePath = config('briefings.storage.path', 'briefings');
        $storagePath = sprintf(
            '%s/%d/%d',
            $basePath,
            $this->generation->workspace_id,
            $this->generation->id,
        );

        $outputPaths = [];
        $requestedFormats = $this->resolveOutputFormats();

        if ($requestedFormats->includes(BriefingOutputFormat::Html)) {
            $htmlContent = $this->renderHtml();
            $htmlPath = $storagePath.'/briefing.html';
            Storage::disk($disk)->put($htmlPath, $htmlContent);
            $outputPaths[BriefingOutputFormat::Html->value] = $htmlPath;
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Pdf)) {
            $pdfDriver = config('briefings.pdf.driver');
            if ($pdfDriver !== null) {
                $pdfPath = $storagePath.'/briefing.pdf';
                $pdfContent = $this->renderPdf();
                Storage::disk($disk)->put($pdfPath, $pdfContent);
                $outputPaths[BriefingOutputFormat::Pdf->value] = $pdfPath;
            } else {
                Log::warning('Briefing PDF generation skipped - driver not configured', [
                    'generation_id' => $this->generation->id,
                ]);
            }
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Markdown)) {
            $markdownContent = $this->renderMarkdown();
            $markdownPath = $storagePath.'/briefing.md';
            Storage::disk($disk)->put($markdownPath, $markdownContent);
            $outputPaths[BriefingOutputFormat::Markdown->value] = $markdownPath;
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Slides)) {
            $slidesContent = $this->renderSlides();
            $slidesPath = $storagePath.'/briefing.slides.json';
            Storage::disk($disk)->put($slidesPath, $slidesContent);
            $outputPaths[BriefingOutputFormat::Slides->value] = $slidesPath;
        }

        // Update the generation with output paths
        $this->generation->update([
            'output_paths' => $outputPaths,
        ]);

        Log::info('Briefing rendered', [
            'generation_id' => $this->generation->id,
            'formats' => array_keys($outputPaths),
        ]);
    }

    /**
     * Render the briefing as HTML.
     */
    private function renderHtml(): string
    {
        $this->generation->loadMissing('briefing', 'workspace');

        /** @var view-string $viewName */
        $viewName = 'briefings.render';

        return view($viewName, [
            'generation' => $this->generation,
            'briefing' => $this->generation->briefing,
            'workspace' => $this->generation->workspace,
            'narrative' => $this->generation->narrative,
            'structuredData' => $this->generation->structured_data,
            'achievements' => $this->generation->achievements,
        ])->render();
    }

    /**
     * Render the briefing as PDF.
     */
    private function renderPdf(): string
    {
        $htmlContent = $this->renderHtml();

        $browsershot = Browsershot::html($htmlContent)
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground();

        $chromePath = config('briefings.pdf.chrome_path');
        if ($chromePath !== null) {
            $browsershot->setChromePath($chromePath);
        }

        Log::info('PDF rendering started', [
            'generation_id' => $this->generation->id,
        ]);

        return $browsershot->pdf();
    }

    /**
     * Render the briefing as Markdown.
     */
    private function renderMarkdown(): string
    {
        $briefing = $this->generation->briefing;
        $narrative = $this->generation->narrative ?? '';

        $markdown = "# {$briefing?->title}\n\n";
        $markdown .= $narrative;

        if (! empty($this->generation->achievements)) {
            $markdown .= "\n\n## Achievements\n\n";
            foreach ($this->generation->achievements as $achievement) {
                $markdown .= sprintf("- **%s**: %s\n", $achievement['title'] ?? 'Achievement', $achievement['description'] ?? '');
            }
        }

        return $markdown;
    }

    /**
     * Render the briefing as a slides JSON payload.
     */
    private function renderSlides(): string
    {
        $structuredData = $this->generation->structured_data ?? [];
        $slides = $structuredData['slides'] ?? null;

        if (! is_array($slides) || ! isset($slides['slides']) || ! is_array($slides['slides'])) {
            throw new RuntimeException('Briefing slides payload is missing or invalid.');
        }

        $encoded = json_encode($slides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode briefing slides payload.');
        }

        return $encoded;
    }

    /**
     * Resolve output formats for the generation.
     *
     * @return BriefingOutputFormats The resolved output formats
     */
    private function resolveOutputFormats(): BriefingOutputFormats
    {
        $briefing = $this->generation->briefing;
        $formats = is_array($briefing?->output_formats)
            ? array_values($briefing->output_formats)
            : null;
        $resolved = BriefingOutputFormats::fromArray($formats);

        if ($resolved->isEmpty()) {
            throw new RuntimeException('Briefing output formats are not configured.');
        }

        return $resolved;
    }
}
