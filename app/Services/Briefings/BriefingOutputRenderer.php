<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\BriefingGeneration;
use App\Services\Briefings\ValueObjects\BriefingOutputFormats;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

final class BriefingOutputRenderer
{
    /**
     * Resolve output format configuration for the given generation.
     */
    public function resolveFormats(BriefingGeneration $generation): BriefingOutputFormats
    {
        $briefing = $generation->briefing;
        $formats = is_array($briefing?->output_formats)
            ? array_values($briefing->output_formats)
            : null;

        $resolvedFormats = BriefingOutputFormats::fromArray($formats);

        if ($resolvedFormats->isEmpty()) {
            throw new RuntimeException('Briefing output formats are not configured.');
        }

        return $resolvedFormats;
    }

    /**
     * Resolve the configured storage disk.
     */
    public function storageDisk(): string
    {
        return (string) config('briefings.storage.disk', 's3');
    }

    /**
     * Resolve the storage path for a generated briefing artifact.
     */
    public function resolveStoragePath(BriefingGeneration $generation, string $filename): string
    {
        $basePath = (string) config('briefings.storage.path', 'briefings');

        return sprintf('%s/%d/%d/%s', $basePath, $generation->workspace_id, $generation->id, $filename);
    }

    /**
     * Render the HTML briefing output.
     */
    public function renderHtml(BriefingGeneration $generation): string
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
    public function renderPdf(BriefingGeneration $generation): string
    {
        $htmlContent = $this->renderHtml($generation);

        $timeout = (int) config('briefings.pdf.timeout', 60);

        $browsershot = Browsershot::html($htmlContent)
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->timeout($timeout);

        $chromePath = config('briefings.pdf.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
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
    public function renderMarkdown(BriefingGeneration $generation): string
    {
        $briefing = $generation->briefing;
        $narrative = $generation->narrative ?? '';

        $markdown = "# {$briefing?->title}\n\n";
        $markdown .= $narrative;

        if (! empty($generation->achievements)) {
            $markdown .= "\n\n## Achievements\n\n";
            foreach ($generation->achievements as $achievement) {
                $markdown .= sprintf('- **%s**: %s'."\n", $achievement['title'] ?? 'Achievement', $achievement['description'] ?? '');
            }
        }

        return $markdown;
    }

    /**
     * Render the slide payload output as JSON.
     */
    public function renderSlides(BriefingGeneration $generation): string
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
}
