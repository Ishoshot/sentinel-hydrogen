<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Enums\BriefingOutputFormat;
use App\Enums\Queue;
use App\Models\BriefingGeneration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class RenderBriefingPdf implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

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

        $outputPaths = $this->generation->output_paths ?? [];

        // Render HTML
        $htmlContent = $this->renderHtml();
        $htmlPath = $storagePath.'/briefing.html';
        Storage::disk($disk)->put($htmlPath, $htmlContent);
        $outputPaths[BriefingOutputFormat::Html->value] = $htmlPath;

        // Render PDF if driver is configured
        $pdfDriver = config('briefings.pdf.driver');
        if ($pdfDriver !== null) {
            $pdfPath = $storagePath.'/briefing.pdf';
            $pdfContent = $this->renderPdf();
            Storage::disk($disk)->put($pdfPath, $pdfContent);
            $outputPaths[BriefingOutputFormat::Pdf->value] = $pdfPath;
        }

        // Render Markdown
        $markdownContent = $this->renderMarkdown();
        $markdownPath = $storagePath.'/briefing.md';
        Storage::disk($disk)->put($markdownPath, $markdownContent);
        $outputPaths[BriefingOutputFormat::Markdown->value] = $markdownPath;

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
        $driver = config('briefings.pdf.driver', 'browsershot');
        // TODO: Implement PDF rendering with Browsershot or similar
        // For now, return empty string as placeholder
        Log::info('PDF rendering requested', [
            'generation_id' => $this->generation->id,
            'driver' => $driver,
        ]);

        return '';
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
}
