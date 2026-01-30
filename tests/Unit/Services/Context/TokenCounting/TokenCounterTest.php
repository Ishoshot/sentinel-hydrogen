<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\AI\TokenCountMode;
use App\Services\Context\TokenCounting\AnthropicTokenCounter;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;
use App\Services\Context\TokenCounting\OpenAiTokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Yethee\Tiktoken\EncoderProvider;

it('falls back to heuristic counting when openai model is unknown', function (): void {
    $text = 'Hello world';
    $counter = new OpenAiTokenCounter(new EncoderProvider());
    $context = new TokenCounterContext(AiProvider::OpenAI, 'unknown-model');
    $expected = (new HeuristicTokenCounter())->countTextTokens($text, $context);

    expect($counter->countTextTokens($text, $context))->toBe($expected);
});

it('uses anthropic count tokens endpoint in precise mode', function (): void {
    Http::fake([
        '*' => Http::response(['input_tokens' => 42], 200),
    ]);

    $counter = new AnthropicTokenCounter(app(Factory::class), new HeuristicTokenCounter());
    $context = new TokenCounterContext(
        AiProvider::Anthropic,
        'claude-3-5-haiku-20241022',
        TokenCountMode::Precise,
        'sk-ant-test'
    );

    expect($counter->countMessageTokens('System', 'User', $context))->toBe(42);
});

it('falls back to heuristic counting when anthropic api key is missing', function (): void {
    $counter = new AnthropicTokenCounter(app(Factory::class), new HeuristicTokenCounter());
    $context = new TokenCounterContext(
        AiProvider::Anthropic,
        'claude-3-5-haiku-20241022',
        TokenCountMode::Precise
    );

    $expected = (new HeuristicTokenCounter())->countMessageTokens('System', 'User', $context);

    expect($counter->countMessageTokens('System', 'User', $context))->toBe($expected);
});
