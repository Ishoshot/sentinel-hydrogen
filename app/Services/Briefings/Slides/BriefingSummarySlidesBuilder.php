<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides;

use App\Models\Briefing;
use App\Services\Briefings\ValueObjects\BriefingSlide;
use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingSlideMetric;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Builds summary-oriented slides for a briefing deck.
 */
final class BriefingSummarySlidesBuilder
{
    /**
     * Build the core title, summary, and metrics slides.
     *
     * @return array<int, BriefingSlide>
     */
    public function build(Briefing $briefing, BriefingStructuredData $structuredData, ?string $narrative): array
    {
        $period = $structuredData->period();
        $summary = $structuredData->summary();

        return [
            $this->buildTitleSlide($briefing->title, $briefing->description, $period->start, $period->end),
            $this->buildSummarySlide($summary, $period->start, $period->end, $narrative),
            $this->buildMetricsSlide($summary),
        ];
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
        $blocks = [];

        if ($description !== null && $description !== '') {
            $blocks[] = BriefingSlideBlock::text($description);
        }

        return new BriefingSlide(
            id: 'title',
            type: 'title',
            title: $title,
            subtitle: $this->formatPeriodRange($periodStart, $periodEnd),
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
        $text = $this->summarizeNarrative($narrative)
            ?? $this->buildSummarySentence($summary, $periodStart, $periodEnd);

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
     * Summarize a narrative for use in a single slide block.
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
}
