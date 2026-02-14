<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates repository guideline documents to section budget.
 */
final readonly class TokenLimitGuidelineSectionTruncator
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
     * Truncate guidelines to fit within a token budget.
     *
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    public function truncate(array $guidelines, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($guidelines as $guideline) {
            $contentTokens = $this->tokenTruncator->estimateTokens($guideline['content']);
            $descriptionTokens = $guideline['description'] !== null
                ? $this->tokenTruncator->estimateTokens($guideline['description'])
                : 0;
            $guidelineTokens = $contentTokens + $descriptionTokens;

            if ($totalTokens + $guidelineTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > AbstractTokenTruncator::MIN_SECTION_TOKENS) {
                    $guideline['content'] = $this->tokenTruncator->truncateText(
                        $guideline['content'],
                        $remaining,
                        '... [truncated - guideline too long]'
                    );
                    $result[] = $guideline;
                }

                break;
            }

            $result[] = $guideline;
            $totalTokens += $guidelineTokens;
        }

        return $result;
    }
}
