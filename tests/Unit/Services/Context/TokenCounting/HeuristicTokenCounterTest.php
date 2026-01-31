<?php

declare(strict_types=1);

use App\Enums\AI\TokenCountMode;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

it('counts text tokens using heuristic formula', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countTextTokens('Hello World', $context);

    expect($result)->toBe(3);
});

it('counts empty text as zero tokens', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countTextTokens('', $context);

    expect($result)->toBe(0);
});

it('rounds up token count', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countTextTokens('A', $context);

    expect($result)->toBe(1);
});

it('handles unicode characters correctly', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countTextTokens('日本語テスト', $context);

    expect($result)->toBe(2);
});

it('counts message tokens with system prompt', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countMessageTokens('System prompt', 'User prompt', $context);

    expect($result)->toBeGreaterThan(0);
});

it('counts message tokens without system prompt', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countMessageTokens(null, 'User prompt only', $context);

    expect($result)->toBe(4);
});

it('uses 0.25 tokens per character ratio', function (): void {
    $counter = new HeuristicTokenCounter();
    $context = new TokenCounterContext();

    $text = str_repeat('a', 100);
    $result = $counter->countTextTokens($text, $context);

    expect($result)->toBe(25);
});

it('ignores context mode for heuristic counting', function (): void {
    $counter = new HeuristicTokenCounter();
    $estimateContext = new TokenCounterContext(mode: TokenCountMode::Estimate);
    $preciseContext = new TokenCounterContext(mode: TokenCountMode::Precise);

    $text = 'Test string';
    $estimateResult = $counter->countTextTokens($text, $estimateContext);
    $preciseResult = $counter->countTextTokens($text, $preciseContext);

    expect($estimateResult)->toBe($preciseResult);
});
