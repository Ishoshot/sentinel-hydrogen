<?php

declare(strict_types=1);

namespace App\Services\Context\TokenCounting;

use App\Services\Context\Contracts\TokenCounter;

/**
 * Heuristic token estimator used when precise counters are unavailable.
 */
final readonly class HeuristicTokenCounter implements TokenCounter
{
    private const float TOKENS_PER_CHAR = 0.25;

    /**
     * {@inheritdoc}
     */
    public function countTextTokens(string $text, TokenCounterContext $context): int
    {
        return (int) ceil(mb_strlen($text) * self::TOKENS_PER_CHAR);
    }

    /**
     * {@inheritdoc}
     */
    public function countMessageTokens(?string $systemPrompt, string $userPrompt, TokenCounterContext $context): int
    {
        $combined = $systemPrompt === null
            ? $userPrompt
            : $systemPrompt."\n".$userPrompt;

        return $this->countTextTokens($combined, $context);
    }
}
