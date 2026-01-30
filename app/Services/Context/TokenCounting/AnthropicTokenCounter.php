<?php

declare(strict_types=1);

namespace App\Services\Context\TokenCounting;

use App\Enums\AI\TokenCountMode;
use App\Services\Context\Contracts\TokenCounter;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Token counter that uses Anthropic's count-tokens endpoint when possible.
 */
final readonly class AnthropicTokenCounter implements TokenCounter
{
    /**
     * Create a new Anthropic token counter.
     */
    public function __construct(
        private Factory $http,
        private HeuristicTokenCounter $fallback,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function countTextTokens(string $text, TokenCounterContext $context): int
    {
        if ($context->mode !== TokenCountMode::Precise || $context->apiKey === null) {
            return $this->fallback->countTextTokens($text, $context);
        }

        return $this->countViaApi(null, $text, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function countMessageTokens(?string $systemPrompt, string $userPrompt, TokenCounterContext $context): int
    {
        if ($context->mode !== TokenCountMode::Precise || $context->apiKey === null) {
            return $this->fallback->countMessageTokens($systemPrompt, $userPrompt, $context);
        }

        return $this->countViaApi($systemPrompt, $userPrompt, $context);
    }

    /**
     * Count tokens by calling Anthropic's count-tokens API.
     */
    private function countViaApi(?string $systemPrompt, string $userPrompt, TokenCounterContext $context): int
    {
        $model = $context->model;
        if ($model === null) {
            return $this->fallback->countMessageTokens($systemPrompt, $userPrompt, $context);
        }

        $apiKey = $context->apiKey;
        if ($apiKey === null) {
            return $this->fallback->countMessageTokens($systemPrompt, $userPrompt, $context);
        }

        try {
            $response = $this->http
                ->withHeaders($this->buildHeaders($apiKey))
                ->timeout(15)
                ->post($this->resolveEndpoint(), $this->buildPayload($model, $systemPrompt, $userPrompt))
                ->throw();

            $inputTokens = $response->json('input_tokens');

            if (is_int($inputTokens)) {
                return $inputTokens;
            }
        } catch (Throwable $throwable) {
            Log::warning('Anthropic token counting failed, falling back to heuristic', [
                'error' => $throwable->getMessage(),
            ]);
        }

        return $this->fallback->countMessageTokens($systemPrompt, $userPrompt, $context);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $apiKey): array
    {
        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => (string) config('prism.providers.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ];

        $betaHeader = config('prism.providers.anthropic.anthropic_beta');
        if (is_string($betaHeader) && $betaHeader !== '') {
            $headers['anthropic-beta'] = $betaHeader;
        }

        return $headers;
    }

    /**
     * @return array{model: string, messages: array<int, array{role: string, content: string}>, system?: string}
     */
    private function buildPayload(string $model, ?string $systemPrompt, string $userPrompt): array
    {
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        if ($systemPrompt !== null && $systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        return $payload;
    }

    /**
     * Resolve the Anthropic count-tokens endpoint URL.
     */
    private function resolveEndpoint(): string
    {
        $baseUrl = (string) config('prism.providers.anthropic.url', 'https://api.anthropic.com/v1');

        return mb_rtrim($baseUrl, '/').'/messages/count_tokens';
    }
}
