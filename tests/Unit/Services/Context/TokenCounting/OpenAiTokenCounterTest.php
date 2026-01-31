<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Services\Context\TokenCounting\OpenAiTokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

it('counts text tokens', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(
        provider: AiProvider::OpenAI,
        model: 'gpt-4',
    );

    $result = $counter->countTextTokens('Hello, world!', $context);

    expect($result)->toBeGreaterThan(0);
});

it('counts tokens for empty text', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext();

    $result = $counter->countTextTokens('', $context);

    expect($result)->toBe(0);
});

it('counts message tokens with system prompt', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(model: 'gpt-4');

    $result = $counter->countMessageTokens(
        'You are a helpful assistant.',
        'Hello!',
        $context,
    );

    expect($result)->toBeGreaterThan(0);
});

it('counts message tokens without system prompt', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(model: 'gpt-4');

    $result = $counter->countMessageTokens(null, 'Hello, how are you?', $context);

    expect($result)->toBeGreaterThan(0);
});

it('uses default model when model is null', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(model: null);

    $result = $counter->countTextTokens('Test message', $context);

    expect($result)->toBeGreaterThan(0);
});

it('uses default model when model is empty string', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(model: '');

    $result = $counter->countTextTokens('Test message', $context);

    expect($result)->toBeGreaterThan(0);
});

it('handles long text', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(model: 'gpt-4');

    $longText = str_repeat('This is a test sentence. ', 100);
    $result = $counter->countTextTokens($longText, $context);

    expect($result)->toBeGreaterThan(100);
});

it('falls back to heuristic when encoder fails', function (): void {
    $counter = new OpenAiTokenCounter();
    $context = new TokenCounterContext(model: 'invalid-model-that-does-not-exist-xyz');

    $result = $counter->countTextTokens('Test message', $context);

    expect($result)->toBeGreaterThan(0);
});
