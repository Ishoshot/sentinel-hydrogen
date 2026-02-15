<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides\Strategies;

use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Resolves the text content for summary slides.
 */
final readonly class SlideSummaryTextStrategy
{
    /**
     * Resolve the summary text from narrative or metrics.
     */
    public function resolve(BriefingSummary $summary, string $periodStart, string $periodEnd, ?string $narrative): string
    {
        return $this->summarizeNarrative($narrative)
            ?? $this->buildSummarySentence($summary, $periodStart, $periodEnd);
    }

    /**
     * Format a period range for display.
     */
    public function formatPeriodRange(string $periodStart, string $periodEnd): ?string
    {
        $start = mb_trim($periodStart);
        $end = mb_trim($periodEnd);

        if ($start === '' || $end === '') {
            return null;
        }

        return sprintf('%s to %s', $start, $end);
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
}
