<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates repository metadata context (README/CONTRIBUTING).
 */
final readonly class TokenLimitRepositoryContextTruncator
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
     * Truncate repository context to fit within a token budget.
     *
     * @param  array{readme?: string|null, contributing?: string|null}  $context
     * @return array{readme?: string|null, contributing?: string|null}
     */
    public function truncate(array $context, int $maxTokens): array
    {
        if ($context === []) {
            return $context;
        }

        $totalTokens = 0;

        foreach (['contributing', 'readme'] as $key) {
            if (! isset($context[$key])) {
                continue;
            }

            if ($context[$key] === null) {
                continue;
            }

            $content = $context[$key];
            $contentTokens = $this->tokenTruncator->estimateTokens($content);

            if ($totalTokens + $contentTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $context[$key] = $this->tokenTruncator->truncateText(
                        $content,
                        $remaining,
                        '... [truncated - repository context too long]'
                    );
                    $totalTokens = $maxTokens;
                } else {
                    unset($context[$key]);
                }

                continue;
            }

            $totalTokens += $contentTokens;
        }

        return $context;
    }
}
