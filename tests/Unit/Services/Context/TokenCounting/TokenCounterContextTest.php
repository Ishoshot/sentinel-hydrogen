<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\AI\TokenCountMode;
use App\Services\Context\TokenCounting\TokenCounterContext;

it('can be constructed with all parameters', function (): void {
    $context = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3-sonnet',
        mode: TokenCountMode::Precise,
        apiKey: 'sk-test-key',
    );

    expect($context->provider)->toBe(AiProvider::Anthropic);
    expect($context->model)->toBe('claude-3-sonnet');
    expect($context->mode)->toBe(TokenCountMode::Precise);
    expect($context->apiKey)->toBe('sk-test-key');
});

it('can be constructed with default values', function (): void {
    $context = new TokenCounterContext();

    expect($context->provider)->toBeNull();
    expect($context->model)->toBeNull();
    expect($context->mode)->toBe(TokenCountMode::Estimate);
    expect($context->apiKey)->toBeNull();
});

it('creates from metadata with provider and model', function (): void {
    $context = TokenCounterContext::fromMetadata([
        'token_counter_provider' => 'openai',
        'token_counter_model' => 'gpt-4',
    ]);

    expect($context->provider)->toBe(AiProvider::OpenAI);
    expect($context->model)->toBe('gpt-4');
    expect($context->mode)->toBe(TokenCountMode::Estimate);
});

it('creates from metadata with custom mode', function (): void {
    $context = TokenCounterContext::fromMetadata(
        ['token_counter_provider' => 'anthropic'],
        TokenCountMode::Precise,
    );

    expect($context->mode)->toBe(TokenCountMode::Precise);
});

it('handles invalid provider in metadata', function (): void {
    $context = TokenCounterContext::fromMetadata([
        'token_counter_provider' => 'invalid_provider',
    ]);

    expect($context->provider)->toBeNull();
});

it('handles non-string values in metadata', function (): void {
    $context = TokenCounterContext::fromMetadata([
        'token_counter_provider' => 123,
        'token_counter_model' => ['not', 'a', 'string'],
    ]);

    expect($context->provider)->toBeNull();
    expect($context->model)->toBeNull();
});

it('handles missing keys in metadata', function (): void {
    $context = TokenCounterContext::fromMetadata([]);

    expect($context->provider)->toBeNull();
    expect($context->model)->toBeNull();
});

it('creates copy with different mode', function (): void {
    $original = new TokenCounterContext(
        provider: AiProvider::Anthropic,
        model: 'claude-3',
        mode: TokenCountMode::Estimate,
        apiKey: 'original-key',
    );

    $copy = $original->withMode(TokenCountMode::Precise);

    expect($original->mode)->toBe(TokenCountMode::Estimate);
    expect($copy->mode)->toBe(TokenCountMode::Precise);
    expect($copy->provider)->toBe(AiProvider::Anthropic);
    expect($copy->model)->toBe('claude-3');
    expect($copy->apiKey)->toBe('original-key');
});

it('creates copy with different mode and api key', function (): void {
    $original = new TokenCounterContext(
        provider: AiProvider::OpenAI,
        model: 'gpt-4',
        mode: TokenCountMode::Estimate,
    );

    $copy = $original->withMode(TokenCountMode::Precise, 'new-api-key');

    expect($copy->mode)->toBe(TokenCountMode::Precise);
    expect($copy->apiKey)->toBe('new-api-key');
});
