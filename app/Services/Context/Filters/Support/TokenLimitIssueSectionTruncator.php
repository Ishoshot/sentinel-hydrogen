<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates linked issue payloads to stay within a section budget.
 */
final readonly class TokenLimitIssueSectionTruncator
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
     * Truncate linked issues to fit within budget.
     *
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $issues
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    public function truncate(array $issues, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($issues as $issue) {
            $issueTokens = $this->estimateIssueTokens($issue);

            if ($totalTokens + $issueTokens > $maxTokens) {
                if ($totalTokens < $maxTokens - AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $remainingBudget = $maxTokens - $totalTokens - 200;
                    $result[] = $this->truncateIssue($issue, $remainingBudget);
                }

                break;
            }

            $result[] = $issue;
            $totalTokens += $issueTokens;
        }

        return $result;
    }

    /**
     * Estimate tokens for an issue including comments.
     *
     * @param  array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}  $issue
     */
    private function estimateIssueTokens(array $issue): int
    {
        $tokens = $this->tokenTruncator->estimateTokens($issue['title']);
        $tokens += $this->tokenTruncator->estimateTokens($issue['body'] ?? '');

        foreach ($issue['comments'] as $comment) {
            $tokens += $this->tokenTruncator->estimateTokens($comment['body']);
        }

        return $tokens;
    }

    /**
     * @param  array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}  $issue
     * @return array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}
     */
    private function truncateIssue(array $issue, int $maxTokens): array
    {
        if ($issue['body'] !== null) {
            $bodyTokens = $this->tokenTruncator->estimateTokens($issue['body']);
            if ($bodyTokens > $maxTokens / 2) {
                $issue['body'] = $this->tokenTruncator->truncateText($issue['body'], (int) ($maxTokens / 2), '... [truncated]');
            }
        }

        $issue['comments'] = array_slice($issue['comments'], 0, 3);

        return $issue;
    }
}
