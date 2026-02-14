<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingOutputFormat;
use App\Models\BriefingGeneration;
use App\Services\Briefings\ValueObjects\BriefingOutputFormats;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

final class RenderBriefingOutputs
{
    /**
     * Render and persist configured output formats for a completed briefing generation.
     */
    public function handle(BriefingGeneration $generation): void
    {
        $generation->loadMissing('briefing');

        $disk = config('briefings.storage.disk', 'r2');
        $basePath = config('briefings.storage.path', 'briefings');
        $storagePath = sprintf('%s/%d/%d', $basePath, $generation->workspace_id, $generation->id);

        $outputPaths = [];
        $requestedFormats = $this->resolveOutputFormats($generation);

        if ($requestedFormats->includes(BriefingOutputFormat::Html)) {
            $htmlContent = $this->renderHtml($generation);
            $htmlPath = $storagePath.'/briefing.html';
            Storage::disk($disk)->put($htmlPath, $htmlContent);
            $outputPaths[BriefingOutputFormat::Html->value] = $htmlPath;
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Pdf)) {
            $pdfDriver = config('briefings.pdf.driver');

            if ($pdfDriver !== null) {
                $pdfPath = $storagePath.'/briefing.pdf';
                $pdfContent = $this->renderPdf($generation);
                Storage::disk($disk)->put($pdfPath, $pdfContent);
                $outputPaths[BriefingOutputFormat::Pdf->value] = $pdfPath;
            } else {
                Log::warning('Briefing PDF generation skipped - driver not configured', [
                    'generation_id' => $generation->id,
                ]);
            }
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Markdown)) {
            $markdownContent = $this->renderMarkdown($generation);
            $markdownPath = $storagePath.'/briefing.md';
            Storage::disk($disk)->put($markdownPath, $markdownContent);
            $outputPaths[BriefingOutputFormat::Markdown->value] = $markdownPath;
        }

        if ($requestedFormats->includes(BriefingOutputFormat::Slides)) {
            $slidesContent = $this->renderSlides($generation);
            $slidesPath = $storagePath.'/briefing.slides.json';
            Storage::disk($disk)->put($slidesPath, $slidesContent);
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
     * Render the HTML briefing output.
     */
    private function renderHtml(BriefingGeneration $generation): string
    {
        $generation->loadMissing('briefing', 'workspace');

        /** @var view-string $viewName */
        $viewName = 'briefings.render';

        return view($viewName, [
            'generation' => $generation,
            'briefing' => $generation->briefing,
            'workspace' => $generation->workspace,
            'narrative' => $generation->narrative,
            'structuredData' => $generation->structured_data,
            'achievements' => $generation->achievements,
        ])->render();
    }

    /**
     * Render a PDF briefing output from the HTML representation.
     */
    private function renderPdf(BriefingGeneration $generation): string
    {
        $htmlContent = $this->renderHtml($generation);

        $browsershot = Browsershot::html($htmlContent)
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground();

        $chromePath = config('briefings.pdf.chrome_path');
        if ($chromePath !== null) {
            $browsershot->setChromePath($chromePath);
        }

        Log::info('PDF rendering started', [
            'generation_id' => $generation->id,
        ]);

        return $browsershot->pdf();
    }

    /**
     * Render the markdown briefing output.
     */
    private function renderMarkdown(BriefingGeneration $generation): string
    {
        $briefing = $generation->briefing;
        $narrative = $generation->narrative ?? '';

        $markdown = "# {$briefing?->title}\n\n";
        $markdown .= $narrative;

        if (! empty($generation->achievements)) {
            $markdown .= "\n\n## Achievements\n\n";
            foreach ($generation->achievements as $achievement) {
                $markdown .= sprintf("- **%s**: %s\n", $achievement['title'] ?? 'Achievement', $achievement['description'] ?? '');
            }
        }

        return $markdown;
    }

    /**
     * Render the slide payload output as JSON.
     */
    private function renderSlides(BriefingGeneration $generation): string
    {
        $structuredData = $generation->structured_data ?? [];
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
     * Resolve output format configuration for the given generation.
     */
    private function resolveOutputFormats(BriefingGeneration $generation): BriefingOutputFormats
    {
        $briefing = $generation->briefing;
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
