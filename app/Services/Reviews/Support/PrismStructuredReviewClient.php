<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Structured\Response as StructuredResponse;

/**
 * Executes structured review requests through Prism.
 */
final class PrismStructuredReviewClient
{
    /**
     * Execute a structured review call.
     */
    public function execute(
        string $provider,
        string $model,
        string $apiKey,
        ObjectSchema $schema,
        string $systemPrompt,
        string $userPrompt,
        int $outputBudget,
        bool $enableThinking,
    ): StructuredResponse {
        $providerOptions = ['use_tool_calling' => true];

        if ($enableThinking) {
            $providerOptions['thinking'] = ['enabled' => true];
        }

        $temperature = $enableThinking ? 1 : 0.1;

        return Prism::structured()
            ->using($provider, $model, ['api_key' => $apiKey])
            ->withSchema($schema)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens($outputBudget)
            ->usingTemperature($temperature)
            ->withProviderOptions($providerOptions)
            ->withClientOptions(['timeout' => 420])
            ->asStructured();
    }
}
