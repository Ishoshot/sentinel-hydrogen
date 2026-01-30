<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Services\Context\SensitiveDataRedactor;

/**
 * Sanitizes and truncates tool call results before persistence.
 */
final readonly class CommandToolResultSanitizer
{
    private const int MAX_TOOL_RESULT_CHARS = 800;

    private const int MAX_ARGUMENT_CHARS = 200;

    private const int MAX_ERROR_CHARS = 300;

    /**
     * Create a new CommandToolResultSanitizer instance.
     */
    public function __construct(
        private SensitiveDataRedactor $redactor,
    ) {}

    /**
     * Sanitize tool calls for safe storage.
     *
     * @param  array<int, array{name: string, arguments: array<array-key, mixed>, result: string}>  $toolCalls
     * @return array<int, array{name: string, arguments: array<array-key, mixed>, result: string, result_truncated: bool}>
     */
    public function sanitizeToolCalls(array $toolCalls): array
    {
        return array_values(array_map(function (array $toolCall): array {
            $name = $toolCall['name'];
            $arguments = $this->sanitizeArguments($toolCall['arguments']);
            $result = $this->sanitizeText($toolCall['result'], self::MAX_TOOL_RESULT_CHARS);

            return [
                'name' => $name,
                'arguments' => $arguments,
                'result' => $result['value'],
                'result_truncated' => $result['truncated'],
            ];
        }, $toolCalls));
    }

    /**
     * Sanitize error messages for safe storage.
     */
    public function sanitizeErrorMessage(string $message): string
    {
        $result = $this->sanitizeText($message, self::MAX_ERROR_CHARS);

        return $result['value'];
    }

    /**
     * Sanitize arguments by redacting sensitive data and truncating long strings.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    private function sanitizeArguments(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                $arguments[$key] = $this->sanitizeArguments($value);

                continue;
            }

            if (is_string($value)) {
                $result = $this->sanitizeText($value, self::MAX_ARGUMENT_CHARS);
                $arguments[$key] = $result['value'];

                continue;
            }

            $arguments[$key] = $value;
        }

        return $arguments;
    }

    /**
     * Sanitize and truncate text.
     *
     * @return array{value: string, truncated: bool}
     */
    private function sanitizeText(string $text, int $maxLength): array
    {
        $redacted = $this->redactor->redact($text);
        $length = mb_strlen($redacted);

        if ($length <= $maxLength) {
            return ['value' => $redacted, 'truncated' => false];
        }

        $truncated = mb_substr($redacted, 0, max(0, $maxLength - 3)).'...';

        return ['value' => $truncated, 'truncated' => true];
    }
}
