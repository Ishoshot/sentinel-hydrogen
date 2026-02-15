<?php

declare(strict_types=1);

namespace App\Services\Commands\Clients;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Tool as PrismTool;

final class CommandAgentPrismClient
{
    /**
     * @param  array<int, PrismTool>  $tools
     * @param  array<string, mixed>  $providerOptions
     */
    public function execute(
        Provider $provider,
        string $model,
        string $apiKey,
        string $systemPrompt,
        string $userMessage,
        array $tools,
        float $temperature,
        array $providerOptions,
        int $maxIterations,
    ): TextResponse {
        return Prism::text()
            ->using($provider, $model, ['api_key' => $apiKey])
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userMessage)
            ->withTools($tools)
            ->withToolChoice(ToolChoice::Auto)
            ->withMaxSteps($maxIterations)
            ->withMaxTokens(4096)
            ->usingTemperature($temperature)
            ->withProviderOptions($providerOptions)
            ->withClientOptions(['timeout' => 300])
            ->asText();
    }
}
