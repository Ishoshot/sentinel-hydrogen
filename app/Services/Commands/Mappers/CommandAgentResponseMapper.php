<?php

declare(strict_types=1);

namespace App\Services\Commands\Support;

use App\Services\Commands\ValueObjects\ToolCall;
use Prism\Prism\Text\Response as TextResponse;

final readonly class CommandAgentResponseMapper
{
    /**
     * @return array<int, ToolCall>
     */
    public function mapToolCalls(TextResponse $response): array
    {
        /** @var array<int, array{name: string, arguments: array<string, mixed>, result: string}> $allToolCalls */
        $allToolCalls = [];

        foreach ($response->steps as $step) {
            foreach ($step->toolCalls as $toolCall) {
                $allToolCalls[] = [
                    'name' => $toolCall->name,
                    'arguments' => $toolCall->arguments(),
                    'result' => '',
                ];
            }
        }

        foreach ($response->toolResults as $index => $toolResult) {
            if (! isset($allToolCalls[$index])) {
                continue;
            }

            $result = $toolResult->result;
            $allToolCalls[$index]['result'] = is_array($result)
                ? json_encode($result, JSON_THROW_ON_ERROR)
                : (string) ($result ?? '');
        }

        return array_map(
            fn (array $toolCall): ToolCall => new ToolCall(
                name: $toolCall['name'],
                arguments: $toolCall['arguments'],
                result: $toolCall['result'],
            ),
            array_values($allToolCalls)
        );
    }

    /**
     * @return array{
     *   input_tokens: int,
     *   output_tokens: int,
     *   thinking_tokens: int,
     *   cache_creation_input_tokens: int,
     *   cache_read_input_tokens: int
     * }
     */
    public function extractUsageMetrics(TextResponse $response): array
    {
        /** @var array<string, mixed> $usageData */
        $usageData = get_object_vars($response->usage);
        /** @var array<string, mixed> $metaData */
        $metaData = get_object_vars($response->meta);

        return [
            'input_tokens' => $response->usage->promptTokens,
            'output_tokens' => $response->usage->completionTokens,
            'thinking_tokens' => $this->firstNumericValue(
                [$metaData, $usageData],
                ['thinkingTokens', 'thoughtTokens']
            ),
            'cache_creation_input_tokens' => $this->firstNumericValue(
                [$usageData],
                ['cacheCreationInputTokens', 'cacheWriteInputTokens']
            ),
            'cache_read_input_tokens' => $this->firstNumericValue(
                [$usageData],
                ['cacheReadInputTokens']
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, string>  $keys
     */
    private function firstNumericValue(array $sources, array $keys): int
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $source)) {
                    continue;
                }

                $value = $source[$key];

                if (is_int($value)) {
                    return $value;
                }

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        return 0;
    }
}
