<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates project metadata context (deps/framework/runtime).
 */
final readonly class TokenLimitProjectContextTruncator
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
     * Truncate project context to fit within a token budget.
     *
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $context
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function truncate(array $context, int $maxTokens): array
    {
        if ($context === []) {
            return $context;
        }

        $currentTokens = $this->tokenTruncator->estimateTokens(json_encode($context) ?: '');
        if ($currentTokens <= $maxTokens) {
            return $context;
        }

        if (isset($context['dependencies'])) {
            $context['dependencies'] = array_slice($context['dependencies'], 0, 10);
        }

        $currentTokens = $this->tokenTruncator->estimateTokens(json_encode($context) ?: '');
        if ($currentTokens <= $maxTokens) {
            return $context;
        }

        if (isset($context['frameworks'])) {
            $context['frameworks'] = array_slice($context['frameworks'], 0, 3);
        }

        $currentTokens = $this->tokenTruncator->estimateTokens(json_encode($context) ?: '');
        if ($currentTokens <= $maxTokens) {
            return $context;
        }

        if (isset($context['languages'])) {
            $context['languages'] = array_slice($context['languages'], 0, 3);
        }

        return $context;
    }
}
