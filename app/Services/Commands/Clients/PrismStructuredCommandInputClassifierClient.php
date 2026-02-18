<?php

declare(strict_types=1);

namespace App\Services\Commands\Clients;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Structured\Response as StructuredResponse;

final class PrismStructuredCommandInputClassifierClient
{
    /**
     * Execute command input classification with structured Prism output.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function execute(
        string $provider,
        string $model,
        string $apiKey,
        ObjectSchema $schema,
        string $systemPrompt,
        string $userPrompt,
        array $providerOptions = [],
    ): StructuredResponse {
        $resolvedProviderOptions = array_merge($providerOptions, ['use_tool_calling' => true]);

        return Prism::structured()
            ->using($provider, $model, ['api_key' => $apiKey])
            ->withSchema($schema)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens(400)
            ->usingTemperature(0.0)
            ->withProviderOptions($resolvedProviderOptions)
            ->withClientOptions(['timeout' => 45])
            ->asStructured();
    }
}
