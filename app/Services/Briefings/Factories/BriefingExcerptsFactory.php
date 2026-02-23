<?php

declare(strict_types=1);

namespace App\Services\Briefings\Factories;

use App\Services\Briefings\ValueObjects\BriefingExcerpts;
use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Builds channel-specific briefing excerpts.
 */
final class BriefingExcerptsFactory
{
    /**
     * Generate smart excerpts for multiple channels.
     */
    public function generate(string $narrative, BriefingSummary $summary): BriefingExcerpts
    {
        $normalized = $this->normalizeLineEndings($narrative);

        return BriefingExcerpts::fromArray([
            'short' => $this->generateShortExcerpt($summary),
            'slack' => $this->generateSlackExcerpt($normalized, $summary),
            'email' => $this->generateEmailExcerpt($normalized, $summary),
            'linkedin' => $this->generateLinkedInExcerpt($normalized, $summary),
        ]);
    }

    /**
     * Generate a short excerpt.
     */
    private function generateShortExcerpt(BriefingSummary $summary): string
    {
        return sprintf(
            '%d completed, %d in progress',
            $summary->completed(),
            $summary->inProgress()
        );
    }

    /**
     * Generate a Slack-formatted excerpt.
     */
    private function generateSlackExcerpt(string $narrative, BriefingSummary $summary): string
    {
        $parts = explode("\n\n", $narrative, 2);
        $headline = mb_trim($parts[0] ?? '') !== '' ? $parts[0] : $this->buildSummarySentence($summary);

        return sprintf(
            "*Team Update*\n\n%s\n\n:chart_with_upwards_trend: %d completed | :hourglass: %d in progress",
            $headline,
            $summary->completed(),
            $summary->inProgress()
        );
    }

    /**
     * Generate an email-formatted excerpt.
     */
    private function generateEmailExcerpt(string $narrative, BriefingSummary $summary): string
    {
        if (mb_trim($narrative) === '') {
            return $this->buildSummarySentence($summary);
        }

        $parts = explode("\n\n", $narrative, 3);

        return implode("\n\n", array_slice($parts, 0, 2));
    }

    /**
     * Generate a LinkedIn-formatted excerpt.
     */
    private function generateLinkedInExcerpt(string $narrative, BriefingSummary $summary): string
    {
        if (mb_trim($narrative) === '') {
            return sprintf(
                'Our engineering team shipped %d improvements this period. %s',
                $summary->completed(),
                $this->buildSummarySentence($summary)
            );
        }

        return sprintf(
            "Our engineering team shipped %d improvements this week. Here's what we learned...\n\n%s",
            $summary->completed(),
            mb_substr($narrative, 0, 200).'...'
        );
    }

    /**
     * Normalize line endings to unix-style and collapse 3+ newlines into double.
     */
    private function normalizeLineEndings(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }

    /**
     * Build a concise summary sentence from briefing metrics.
     */
    private function buildSummarySentence(BriefingSummary $summary): string
    {
        $parts = [
            sprintf('%d completed', $summary->completed()),
            sprintf('%d in progress', $summary->inProgress()),
        ];

        if ($summary->failed() > 0) {
            $parts[] = sprintf('%d failed', $summary->failed());
        }

        $sentence = implode(', ', $parts).'.';

        if ($summary->repositoryCount() > 0) {
            $sentence .= sprintf(' %d repositories active.', $summary->repositoryCount());
        }

        return $sentence;
    }
}
