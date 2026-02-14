<?php

declare(strict_types=1);

namespace App\Services\Briefings\Support;

use App\Services\Briefings\ValueObjects\BriefingAiConfiguration;
use App\Services\Briefings\ValueObjects\NarrativeGenerationResult;
use App\Services\Briefings\ValueObjects\NarrativeGenerationTelemetry;
use Prism\Prism\Facades\Prism;

/**
 * Executes AI narrative generation requests via Prism.
 */
final class BriefingNarrativeClient
{
    /**
     * Generate the narrative text and telemetry from prompt inputs.
     */
    public function generate(string $systemPrompt, string $prompt, BriefingAiConfiguration $aiConfiguration): NarrativeGenerationResult
    {
        $maxTokens = (int) config('briefings.platform.max_tokens', 2000);
        $providerOptions = $aiConfiguration->apiKey !== null ? ['api_key' => $aiConfiguration->apiKey] : [];

        $startTime = microtime(true);

        $request = Prism::text()
            ->using($aiConfiguration->provider->value, $aiConfiguration->model, $providerOptions)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($prompt);

        if ($maxTokens > 0) {
            $request->withMaxTokens($maxTokens);
        }

        $response = $request->asText();

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $promptTokens = (int) $response->usage->promptTokens;
        $completionTokens = (int) $response->usage->completionTokens;

        return new NarrativeGenerationResult(
            text: (string) $response->text,
            telemetry: new NarrativeGenerationTelemetry(
                provider: $aiConfiguration->provider->value,
                model: $aiConfiguration->model,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $promptTokens + $completionTokens,
                durationMs: $durationMs,
            ),
        );
    }
}
