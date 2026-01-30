<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Briefing;
use App\Services\Briefings\Contracts\BriefingSlidesBuilder;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingSlide;
use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingSlideDeck;
use App\Services\Briefings\ValueObjects\BriefingSlideMetric;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\BriefingSummary;
use Carbon\CarbonImmutable;

/**
 * Build structured slide decks from briefing data.
 */
final class BriefingSlidesBuilderService implements BriefingSlidesBuilder
{
    private const string SCHEMA_VERSION = '1.0';

    /**
     * Build a structured slide deck for a briefing.
     */
    public function build(
        Briefing $briefing,
        BriefingStructuredData $structuredData,
        BriefingAchievements $achievements,
        ?string $narrative,
    ): BriefingSlideDeck {
        $period = $structuredData->period();
        $summary = $structuredData->summary();
        $payload = $structuredData->payload();

        $slides = [];

        $slides[] = $this->buildTitleSlide($briefing->title, $briefing->description, $period->start, $period->end);

        $slides[] = $this->buildSummarySlide($summary, $period->start, $period->end, $narrative);
        $slides[] = $this->buildMetricsSlide($summary);

        $highlightsSlide = $this->buildHighlightsSlide($payload, $achievements);
        if ($highlightsSlide instanceof BriefingSlide) {
            $slides[] = $highlightsSlide;
        }

        $codeHealthSlide = $this->buildCodeHealthSlide($payload);
        if ($codeHealthSlide instanceof BriefingSlide) {
            $slides[] = $codeHealthSlide;
        }

        $dataQualitySlide = $this->buildDataQualitySlide($structuredData);
        if ($dataQualitySlide instanceof BriefingSlide) {
            $slides[] = $dataQualitySlide;
        }

        $evidenceSlide = $this->buildEvidenceSlide($structuredData);
        if ($evidenceSlide instanceof BriefingSlide) {
            $slides[] = $evidenceSlide;
        }

        return new BriefingSlideDeck(
            version: self::SCHEMA_VERSION,
            title: $briefing->title,
            period: $period,
            generatedAt: CarbonImmutable::now()->toIso8601String(),
            slides: $slides,
            meta: [
                'briefing_slug' => $briefing->slug,
            ],
        );
    }

    /**
     * Build the title slide.
     */
    private function buildTitleSlide(
        string $title,
        ?string $description,
        string $periodStart,
        string $periodEnd,
    ): BriefingSlide {
        $subtitle = $this->formatPeriodRange($periodStart, $periodEnd);
        $blocks = [];

        if ($description !== null && $description !== '') {
            $blocks[] = BriefingSlideBlock::text($description);
        }

        return new BriefingSlide(
            id: 'title',
            type: 'title',
            title: $title,
            subtitle: $subtitle,
            blocks: $blocks,
        );
    }

    /**
     * Build a narrative summary slide.
     */
    private function buildSummarySlide(
        BriefingSummary $summary,
        string $periodStart,
        string $periodEnd,
        ?string $narrative,
    ): BriefingSlide {
        $text = $this->summarizeNarrative($narrative);

        if ($text === null) {
            $text = $this->buildSummarySentence($summary, $periodStart, $periodEnd);
        }

        return new BriefingSlide(
            id: 'summary',
            type: 'summary',
            title: 'Summary',
            subtitle: null,
            blocks: [BriefingSlideBlock::text($text)],
        );
    }

    /**
     * Build a metrics slide from summary data.
     */
    private function buildMetricsSlide(BriefingSummary $summary): BriefingSlide
    {
        $metrics = [
            new BriefingSlideMetric('Total Runs', $summary->totalRuns()),
            new BriefingSlideMetric('Completed', $summary->completed()),
            new BriefingSlideMetric('In Progress', $summary->inProgress()),
            new BriefingSlideMetric('Failed', $summary->failed()),
            new BriefingSlideMetric('Active Days', $summary->activeDays()),
            new BriefingSlideMetric('Review Coverage', $summary->reviewCoverage(), '%'),
            new BriefingSlideMetric('Active Repositories', $summary->repositoryCount()),
        ];

        return new BriefingSlide(
            id: 'metrics',
            type: 'metrics',
            title: 'Key Metrics',
            subtitle: null,
            blocks: [BriefingSlideBlock::metrics($metrics)],
        );
    }

    /**
     * Build a highlights slide from achievements and run data.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildHighlightsSlide(array $payload, BriefingAchievements $achievements): ?BriefingSlide
    {
        $items = [];

        if (! $achievements->isEmpty()) {
            foreach ($achievements->items as $achievement) {
                $items[] = sprintf('%s — %s', $achievement->title, $achievement->description);
            }
        }

        $topContributor = $payload['top_contributor'] ?? null;
        if (is_array($topContributor)) {
            $name = isset($topContributor['name']) ? mb_trim((string) $topContributor['name']) : '';
            $prCount = isset($topContributor['pr_count']) ? (int) $topContributor['pr_count'] : 0;
            if ($name !== '' && $prCount > 0) {
                $items[] = sprintf('Top contributor: %s (%d PRs)', $name, $prCount);
            }
        }

        $runs = $payload['runs'] ?? null;
        if (is_array($runs)) {
            foreach (array_slice($runs, 0, 4) as $run) {
                if (! is_array($run)) {
                    continue;
                }

                $prNumber = $run['pr_number'] ?? null;
                $title = isset($run['pr_title']) ? mb_trim((string) $run['pr_title']) : '';
                $status = $run['status'] ?? null;
                $id = $run['id'] ?? null;

                if ($prNumber === null) {
                    continue;
                }

                if ($title === '') {
                    continue;
                }

                if ($status === null) {
                    continue;
                }

                if ($id === null) {
                    continue;
                }

                $items[] = sprintf('PR #%s — %s (%s) [Run %s]', $prNumber, $title, $status, $id);
            }
        }

        if ($items === []) {
            return null;
        }

        return new BriefingSlide(
            id: 'highlights',
            type: 'highlights',
            title: 'Highlights',
            subtitle: null,
            blocks: [BriefingSlideBlock::list($items)],
        );
    }

    /**
     * Build a code health slide if code health data exists.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildCodeHealthSlide(array $payload): ?BriefingSlide
    {
        $codeHealth = $payload['code_health'] ?? null;
        if (! is_array($codeHealth)) {
            return null;
        }

        $metrics = [
            new BriefingSlideMetric('Total Findings', (int) ($codeHealth['total_findings'] ?? 0)),
            new BriefingSlideMetric('Critical', (int) ($codeHealth['critical_issues'] ?? 0)),
            new BriefingSlideMetric('High', (int) ($codeHealth['high_issues'] ?? 0)),
            new BriefingSlideMetric('Medium', (int) ($codeHealth['medium_issues'] ?? 0)),
        ];

        $blocks = [BriefingSlideBlock::metrics($metrics)];

        $criticalFindings = $codeHealth['top_critical_findings'] ?? null;
        if (is_array($criticalFindings) && $criticalFindings !== []) {
            $items = [];
            foreach (array_slice($criticalFindings, 0, 5) as $finding) {
                if (! is_array($finding)) {
                    continue;
                }

                $title = isset($finding['title']) ? mb_trim((string) $finding['title']) : '';
                $filePath = isset($finding['file_path']) ? mb_trim((string) $finding['file_path']) : '';
                $lineStart = $finding['line_start'] ?? null;
                $id = $finding['id'] ?? null;

                if ($title === '') {
                    continue;
                }

                if ($filePath === '') {
                    continue;
                }

                if ($lineStart === null) {
                    continue;
                }

                if ($id === null) {
                    continue;
                }

                $items[] = sprintf('%s — %s:%s [Finding %s]', $title, $filePath, $lineStart, $id);
            }

            if ($items !== []) {
                $blocks[] = BriefingSlideBlock::list($items, 'Top Critical Findings');
            }
        }

        return new BriefingSlide(
            id: 'code-health',
            type: 'code_health',
            title: 'Code Health',
            subtitle: null,
            blocks: $blocks,
        );
    }

    /**
     * Build a data quality slide when gaps exist.
     */
    private function buildDataQualitySlide(BriefingStructuredData $structuredData): ?BriefingSlide
    {
        $dataQuality = $structuredData->dataQuality;
        $notes = $dataQuality->notes;

        if (! $dataQuality->isSparse && $notes === []) {
            return null;
        }

        $items = [];

        if ($dataQuality->isSparse) {
            $items[] = 'Data is sparse for this period; interpret trends cautiously.';
        }

        foreach (array_slice($notes, 0, 5) as $note) {
            $items[] = $note;
        }

        if ($items === []) {
            return null;
        }

        return new BriefingSlide(
            id: 'data-quality',
            type: 'data_quality',
            title: 'Data Quality',
            subtitle: null,
            blocks: [BriefingSlideBlock::list($items)],
        );
    }

    /**
     * Build an evidence slide from the evidence payload.
     */
    private function buildEvidenceSlide(BriefingStructuredData $structuredData): ?BriefingSlide
    {
        $evidence = $structuredData->evidence;

        $items = [];

        if ($evidence->runIds !== []) {
            $items[] = 'Run IDs: '.$this->formatList($evidence->runIds, 10);
        }

        if ($evidence->findingIds !== []) {
            $items[] = 'Finding IDs: '.$this->formatList($evidence->findingIds, 10);
        }

        if ($evidence->repositoryNames !== []) {
            $items[] = 'Repositories: '.$this->formatList($evidence->repositoryNames, 6);
        }

        if ($items === []) {
            return null;
        }

        return new BriefingSlide(
            id: 'evidence',
            type: 'evidence',
            title: 'Evidence',
            subtitle: null,
            blocks: [BriefingSlideBlock::list($items)],
        );
    }

    /**
     * Summarize the narrative for a slide.
     */
    private function summarizeNarrative(?string $narrative): ?string
    {
        if ($narrative === null || mb_trim($narrative) === '') {
            return null;
        }

        $parts = explode("\n\n", $narrative, 2);
        $firstParagraph = $parts[0];

        $trimmed = mb_trim($firstParagraph);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Build a summary sentence from core metrics.
     */
    private function buildSummarySentence(BriefingSummary $summary, string $periodStart, string $periodEnd): string
    {
        $periodRange = $this->formatPeriodRange($periodStart, $periodEnd);
        $prefix = $periodRange !== null ? sprintf('From %s, ', $periodRange) : '';

        if ($summary->totalRuns() === 0) {
            return $prefix !== '' ? $prefix.'no review runs were recorded.' : 'No review runs were recorded.';
        }

        return sprintf(
            '%sthe team completed %d of %d runs with %d in progress.',
            $prefix,
            $summary->completed(),
            $summary->totalRuns(),
            $summary->inProgress(),
        );
    }

    /**
     * Format a period range for display.
     */
    private function formatPeriodRange(string $periodStart, string $periodEnd): ?string
    {
        $start = mb_trim($periodStart);
        $end = mb_trim($periodEnd);

        if ($start === '' || $end === '') {
            return null;
        }

        return sprintf('%s to %s', $start, $end);
    }

    /**
     * @param  array<int, int|string>  $items
     */
    private function formatList(array $items, int $limit): string
    {
        $visible = array_slice($items, 0, $limit);
        $formatted = implode(', ', array_map(static fn (int|string $item): string => (string) $item, $visible));

        $remaining = count($items) - count($visible);
        if ($remaining > 0) {
            return sprintf('%s, +%d more', $formatted, $remaining);
        }

        return $formatted;
    }
}
