<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Filters\Support\TokenLimitBudgetResolver;
use App\Services\Context\Filters\Support\TokenLimitSectionBudgetApplier;
use App\Services\Context\Filters\Support\TokenLimitSectionTruncator;
use App\Services\Context\TokenCounting\TokenCounterContext;
use Illuminate\Support\Facades\Log;

/**
 * Limits context payload size to fit within the model token budget.
 */
final readonly class TokenLimitFilter implements ContextFilter
{
    /**
     * Create a new token limit filter instance.
     */
    public function __construct(
        private TokenLimitBudgetResolver $budgetResolver,
        private TokenLimitSectionTruncator $sectionTruncator,
        private TokenLimitSectionBudgetApplier $sectionBudgetApplier,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'token_limit';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 100;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        $tokenCounterContext = TokenCounterContext::fromMetadata($bag->metadata);
        $this->sectionTruncator->setContext($tokenCounterContext);

        $initialTokens = $this->sectionTruncator->estimateBagTokens($bag);
        $maxContextTokens = $this->budgetResolver->resolveMaxContextTokens($bag->metadata);
        $budgets = $this->budgetResolver->resolveBudgets($maxContextTokens);

        $this->sectionBudgetApplier->apply($bag, $budgets);

        if ($this->sectionTruncator->estimateBagTokens($bag) > $maxContextTokens) {
            $this->sectionTruncator->progressiveTruncation($bag, $maxContextTokens);
        }

        $finalTokens = $this->sectionTruncator->estimateBagTokens($bag);

        if ($initialTokens !== $finalTokens) {
            Log::info('TokenLimitFilter: Truncated context', [
                'initial_tokens' => $initialTokens,
                'final_tokens' => $finalTokens,
                'reduction' => $initialTokens - $finalTokens,
            ]);
        }
    }
}
