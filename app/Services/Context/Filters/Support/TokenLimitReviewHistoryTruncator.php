<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates prior run review history context.
 */
final readonly class TokenLimitReviewHistoryTruncator
{
    /**
     * Create a new instance.
     */
    public function __construct(private AbstractTokenTruncator $tokenTruncator) {}

    /**
     * Set token counting context for the current truncation cycle.
     */
    public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->tokenTruncator->setContext($tokenCounterContext);
    }

    /**
     * Truncate review history to fit within a token budget.
     *
     * @param  array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>  $reviews
     * @return array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>
     */
    public function truncate(array $reviews, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($reviews as $review) {
            $summaryTokens = $this->tokenTruncator->estimateTokens($review['summary']);
            $findingsTokens = $this->tokenTruncator->estimateTokens(json_encode($review['key_findings']) ?: '');
            $reviewTokens = $summaryTokens + $findingsTokens;

            if ($totalTokens + $reviewTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $review['summary'] = $this->tokenTruncator->truncateText(
                        $review['summary'],
                        $remaining,
                        '... [truncated - review history too long]'
                    );
                    $review['key_findings'] = array_slice($review['key_findings'], 0, 5);
                    $result[] = $review;
                }

                break;
            }

            $result[] = $review;
            $totalTokens += $reviewTokens;
        }

        return $result;
    }
}
