<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Shared token estimation and text truncation primitives.
 */
final class AbstractTokenTruncator
{
    public const int MIN_SECTION_TOKENS = 500;

    /**
     * Token counting context derived from request metadata.
     */
    private TokenCounterContext $tokenCounterContext;

    /**
     * Create a new class instance.
     */
    public function __construct(private readonly TokenCounter $tokenCounter)
    {
        $this->tokenCounterContext = TokenCounterContext::fromMetadata([]);
    }

    /**
     * Set token counting context for the current truncation cycle.
     */
    final public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->tokenCounterContext = $tokenCounterContext;
    }

    /**
     * Estimate token count for the given text payload.
     */
    public function estimateTokens(string $text): int
    {
        return $this->tokenCounter->countTextTokens($text, $this->tokenCounterContext);
    }

    /**
     * Truncate text to fit the provided token budget.
     */
    public function truncateText(string $text, int $maxTokens, string $suffix): string
    {
        $suffixTokens = $this->estimateTokens($suffix);
        $budget = max($maxTokens - $suffixTokens, 0);

        if ($this->estimateTokens($text) <= $maxTokens) {
            return $text;
        }

        return $this->trimToTokenBudget($text, $budget).$suffix;
    }

    /**
     * Trim text to fit token budget without adding a suffix.
     */
    private function trimToTokenBudget(string $text, int $maxTokens): string
    {
        if ($maxTokens <= 0) {
            return '';
        }

        $length = mb_strlen($text);
        $low = 0;
        $high = $length;

        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            $candidate = mb_substr($text, 0, $mid);

            if ($this->estimateTokens($candidate) <= $maxTokens) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return mb_substr($text, 0, $low);
    }
}
