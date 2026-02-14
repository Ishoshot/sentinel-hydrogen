<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Manages token counter context state and propagation to sub-truncators.
 */
final class TokenLimitContextManager
{
    /**
     * Token counting context derived from request metadata.
     */
    private TokenCounterContext $tokenCounterContext;

    /**
     * Create a new context manager instance.
     */
    public function __construct(
        private readonly TokenCounter $tokenCounter,
        private readonly TokenLimitFilePatchTruncator $filePatchTruncator,
        private readonly TokenLimitCodeSectionTruncator $codeSectionTruncator,
        private readonly TokenLimitSupplementalSectionTruncator $supplementalSectionTruncator,
    ) {
        $this->tokenCounterContext = TokenCounterContext::fromMetadata([]);
    }

    /**
     * Set the token counter context and propagate to sub-truncators.
     */
    public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->tokenCounterContext = $tokenCounterContext;
        $this->filePatchTruncator->setContext($tokenCounterContext);
        $this->codeSectionTruncator->setContext($tokenCounterContext);
        $this->supplementalSectionTruncator->setContext($tokenCounterContext);
    }

    /**
     * Estimate current context bag tokens using the active token counter context.
     */
    public function estimateBagTokens(ContextBag $bag): int
    {
        return $bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext);
    }

    /**
     * Check whether the bag exceeds the given token budget.
     */
    public function isOverBudget(ContextBag $bag, int $maxTokens): bool
    {
        return $this->estimateBagTokens($bag) > $maxTokens;
    }
}
