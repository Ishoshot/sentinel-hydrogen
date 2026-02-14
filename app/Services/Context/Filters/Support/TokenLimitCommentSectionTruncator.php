<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates pull request comments to section budget.
 */
final readonly class TokenLimitCommentSectionTruncator
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
     * Truncate PR comments to fit within budget.
     *
     * @param  array<int, array{author: string, body: string, created_at: string}>  $comments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    public function truncate(array $comments, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($comments as $comment) {
            $commentTokens = $this->tokenTruncator->estimateTokens($comment['body']);

            if ($totalTokens + $commentTokens > $maxTokens) {
                break;
            }

            $result[] = $comment;
            $totalTokens += $commentTokens;
        }

        return $result;
    }
}
