<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\TokenLimitBudgetResolver;

it('resolves typed budgets with bounded file limits', function (): void {
    $resolver = new TokenLimitBudgetResolver;

    $budgets = $resolver->resolveBudgets(120_000);

    expect($budgets->filesTotal)->toBeInt()
        ->and($budgets->filesPer)->toBeInt()
        ->and($budgets->filesPer)->toBeLessThanOrEqual($budgets->filesTotal)
        ->and($budgets->issues)->toBeGreaterThan(0)
        ->and($budgets->guidelines)->toBeGreaterThan(0);
});

it('applies a minimum context budget floor', function (): void {
    $resolver = new TokenLimitBudgetResolver;

    expect($resolver->resolveMaxContextTokens(['context_token_budget' => 1000]))
        ->toBe(8000)
        ->and($resolver->resolveMaxContextTokens(['context_token_budget' => '1200']))
        ->toBe(8000);
});
