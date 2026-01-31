<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\AI\TokenCountMode;
use App\Services\Context\TokenCounting\AnthropicTokenCounter;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;
use Illuminate\Support\Facades\Http;

it('uses fallback when mode is not precise', function (): void {
    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet',
        mode: TokenCountMode::Estimate,
        apiKey: 'test-key',
    );

    $result = $counter->countTextTokens('Hello world', $context);

    expect($result)->toBe($fallback->countTextTokens('Hello world', $context));
});

it('uses fallback when api key is null', function (): void {
    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet',
        mode: TokenCountMode::Precise,
        apiKey: null,
    );

    $result = $counter->countTextTokens('Hello world', $context);

    expect($result)->toBe($fallback->countTextTokens('Hello world', $context));
});

it('uses fallback for message tokens when mode is not precise', function (): void {
    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3',
        mode: TokenCountMode::Estimate,
        apiKey: 'test-key',
    );

    $result = $counter->countMessageTokens('System', 'User', $context);
    $expected = $fallback->countMessageTokens('System', 'User', $context);

    expect($result)->toBe($expected);
});

it('uses fallback for message tokens when api key is null', function (): void {
    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3',
        mode: TokenCountMode::Precise,
        apiKey: null,
    );

    $result = $counter->countMessageTokens('System', 'User', $context);
    $expected = $fallback->countMessageTokens('System', 'User', $context);

    expect($result)->toBe($expected);
});

it('uses fallback when model is null', function (): void {
    Http::fake([
        '*' => Http::response(['input_tokens' => 100]),
    ]);

    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: null,
        mode: TokenCountMode::Precise,
        apiKey: 'test-key',
    );

    $result = $counter->countTextTokens('Hello world', $context);

    expect($result)->toBe($fallback->countMessageTokens(null, 'Hello world', $context));
});

it('calls api when mode is precise and api key is provided', function (): void {
    Http::fake([
        '*' => Http::response(['input_tokens' => 50]),
    ]);

    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet-20240229',
        mode: TokenCountMode::Precise,
        apiKey: 'sk-test-key',
    );

    $result = $counter->countTextTokens('Hello world', $context);

    expect($result)->toBe(50);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/messages/count_tokens');
    });
});

it('calls api for message tokens with system prompt', function (): void {
    Http::fake([
        '*' => Http::response(['input_tokens' => 75]),
    ]);

    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-opus-20240229',
        mode: TokenCountMode::Precise,
        apiKey: 'sk-test-key',
    );

    $result = $counter->countMessageTokens('Be helpful', 'Hello!', $context);

    expect($result)->toBe(75);
});

it('falls back on api error', function (): void {
    Http::fake([
        '*' => Http::response(['error' => 'rate limited'], 429),
    ]);

    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet-20240229',
        mode: TokenCountMode::Precise,
        apiKey: 'sk-test-key',
    );

    $result = $counter->countTextTokens('Hello world', $context);
    $expected = $fallback->countMessageTokens(null, 'Hello world', $context);

    expect($result)->toBe($expected);
});

it('falls back when response does not contain input_tokens', function (): void {
    Http::fake([
        '*' => Http::response(['some_other_field' => 100]),
    ]);

    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet-20240229',
        mode: TokenCountMode::Precise,
        apiKey: 'sk-test-key',
    );

    $result = $counter->countTextTokens('Hello world', $context);
    $expected = $fallback->countMessageTokens(null, 'Hello world', $context);

    expect($result)->toBe($expected);
});

it('includes correct headers in api request', function (): void {
    Http::fake([
        '*' => Http::response(['input_tokens' => 25]),
    ]);

    $fallback = new HeuristicTokenCounter();
    $counter = new AnthropicTokenCounter(Http::getFacadeRoot(), $fallback);

    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet-20240229',
        mode: TokenCountMode::Precise,
        apiKey: 'sk-test-api-key',
    );

    $counter->countTextTokens('Test', $context);

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'sk-test-api-key')
            && $request->hasHeader('content-type', 'application/json');
    });
});
