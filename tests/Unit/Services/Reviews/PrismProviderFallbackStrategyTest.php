<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Exceptions\NoProviderKeyException;
use App\Services\Reviews\Strategies\PrismProviderFallbackStrategy;
use App\Services\Reviews\ValueObjects\ReviewMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;
use App\Services\Reviews\ValueObjects\ReviewSummary;
use App\Services\SentinelConfig\ValueObjects\ProviderConfig;
use Illuminate\Support\Facades\Log;

function makeStrategyReviewResult(string $provider = 'anthropic'): ReviewResult
{
    return new ReviewResult(
        summary: new ReviewSummary(
            overview: 'Test review',
            verdict: ReviewVerdict::Comment,
            riskLevel: RiskLevel::Low,
        ),
        findings: [],
        metrics: new ReviewMetrics(
            filesChanged: 1,
            linesAdded: 10,
            linesDeleted: 5,
            inputTokens: 500,
            outputTokens: 200,
            tokensUsedEstimated: 700,
            model: 'test-model',
            provider: $provider,
            durationMs: 1000,
        ),
    );
}

it('returns result from the first provider on success', function (): void {
    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);
    $expectedResult = makeStrategyReviewResult('anthropic');

    $result = $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: fn (AiProvider $provider): ReviewResult => $expectedResult,
    );

    expect($result)->toBe($expectedResult);
});

it('falls back to next provider when first fails with fallback enabled', function (): void {
    Log::spy();

    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);
    $expectedResult = makeStrategyReviewResult('openai');
    $attemptCount = 0;

    $result = $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: function (AiProvider $provider) use (&$attemptCount, $expectedResult): ReviewResult {
            $attemptCount++;
            if ($provider === AiProvider::Anthropic) {
                throw new RuntimeException('Anthropic API is down');
            }

            return $expectedResult;
        },
    );

    expect($result)->toBe($expectedResult)
        ->and($attemptCount)->toBe(2);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Provider failed, trying fallback'
            && $context['provider'] === 'anthropic'
            && $context['attempt'] === 1
        )->once();
});

it('throws exception immediately when fallback is disabled', function (): void {
    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: false);

    $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: function (AiProvider $provider): ReviewResult {
            throw new RuntimeException('Provider failed');
        },
    );
})->throws(RuntimeException::class, 'Provider failed');

it('throws NoProviderKeyException immediately when fallback is disabled', function (): void {
    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: false);

    $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: function (AiProvider $provider): ReviewResult {
            throw NoProviderKeyException::forProvider($provider->value);
        },
    );
})->throws(NoProviderKeyException::class);

it('falls back on NoProviderKeyException when fallback is enabled', function (): void {
    Log::spy();

    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);
    $expectedResult = makeStrategyReviewResult('openai');

    $result = $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: function (AiProvider $provider) use ($expectedResult): ReviewResult {
            if ($provider === AiProvider::Anthropic) {
                throw NoProviderKeyException::forProvider('anthropic');
            }

            return $expectedResult;
        },
    );

    expect($result)->toBe($expectedResult);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Provider key not available, trying fallback'
            && $context['provider'] === 'anthropic'
        )->once();
});

it('throws last exception when all providers fail', function (): void {
    Log::spy();

    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);

    $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: function (AiProvider $provider): ReviewResult {
            throw new RuntimeException("Provider {$provider->value} failed");
        },
    );
})->throws(RuntimeException::class, 'Provider openai failed');

it('respects max fallback attempts limit of 3', function (): void {
    Log::spy();

    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);
    $attemptCount = 0;

    $providers = [AiProvider::Anthropic, AiProvider::OpenAI, AiProvider::Anthropic, AiProvider::OpenAI];

    try {
        $strategy->execute(
            providersToTry: $providers,
            providerConfig: $config,
            executeAttempt: function (AiProvider $provider) use (&$attemptCount): ReviewResult {
                $attemptCount++;
                throw new RuntimeException("Provider {$provider->value} failed");
            },
        );
    } catch (RuntimeException) {
        // Expected
    }

    expect($attemptCount)->toBe(3);
});

it('throws NoProviderKeyException when no providers are given', function (): void {
    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);

    $strategy->execute(
        providersToTry: [],
        providerConfig: $config,
        executeAttempt: fn (AiProvider $provider): ReviewResult => makeStrategyReviewResult(),
    );
})->throws(NoProviderKeyException::class, 'No provider keys configured for this repository');

it('tries only one provider when fallback is disabled even with multiple providers', function (): void {
    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: false);
    $attemptCount = 0;

    try {
        $strategy->execute(
            providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
            providerConfig: $config,
            executeAttempt: function (AiProvider $provider) use (&$attemptCount): ReviewResult {
                $attemptCount++;
                throw new RuntimeException('Fail');
            },
        );
    } catch (RuntimeException) {
        // Expected
    }

    expect($attemptCount)->toBe(1);
});

it('limits max attempts to provider count when fewer than 3', function (): void {
    Log::spy();

    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);
    $attemptCount = 0;

    try {
        $strategy->execute(
            providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
            providerConfig: $config,
            executeAttempt: function (AiProvider $provider) use (&$attemptCount): ReviewResult {
                $attemptCount++;
                throw new RuntimeException('Fail');
            },
        );
    } catch (RuntimeException) {
        // Expected
    }

    expect($attemptCount)->toBe(2);
});

it('returns first successful result even after previous failures', function (): void {
    Log::spy();

    $strategy = new PrismProviderFallbackStrategy;
    $config = new ProviderConfig(fallback: true);
    $expectedResult = makeStrategyReviewResult('openai');

    $callOrder = [];

    $result = $strategy->execute(
        providersToTry: [AiProvider::Anthropic, AiProvider::OpenAI],
        providerConfig: $config,
        executeAttempt: function (AiProvider $provider) use (&$callOrder, $expectedResult): ReviewResult {
            $callOrder[] = $provider->value;
            if ($provider === AiProvider::Anthropic) {
                throw NoProviderKeyException::forProvider('anthropic');
            }

            return $expectedResult;
        },
    );

    expect($result)->toBe($expectedResult)
        ->and($callOrder)->toBe(['anthropic', 'openai']);
});
