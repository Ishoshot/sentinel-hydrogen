<?php

declare(strict_types=1);

namespace App\Services\Commands\Builders;

use App\Enums\Commands\CommandType;
use App\Services\Context\SensitiveDataRedactor;
use App\Support\PromptRenderer;

/**
 * Builds prompts for command execution.
 */
final readonly class CommandPromptBuilder
{
    public const string SYSTEM_PROMPT_VERSION = 'command-system@3';

    public const string USER_PROMPT_VERSION = 'command-user@6';

    /**
     * Create a new CommandPromptBuilder instance.
     */
    public function __construct(
        private PromptRenderer $renderer,
        private SensitiveDataRedactor $sensitiveDataRedactor,
    ) {}

    /**
     * Build the system prompt for a command type.
     */
    public function buildSystemPrompt(CommandType $commandType): string
    {
        return $this->renderer->render('prompts.commands.system', [
            'command_view' => $this->resolveCommandView($commandType),
        ]);
    }

    /**
     * Build the user message from the command run details.
     *
     * @param  array{files?: array<string>, symbols?: array<string>, lines?: array<array{start: int, end: int|null}>}|null  $contextHints
     * @param  array{
     *     decision?: string,
     *     risk_level?: string,
     *     risk_types?: array<int, string>,
     *     confidence?: float|int,
     *     signals?: array<int, array{source?: string, code?: string, severity?: string}>
     * }|null  $inputClassification
     */
    public function buildUserMessage(
        CommandType $commandType,
        string $query,
        ?string $untrustedContext = null,
        ?array $contextHints = null,
        ?array $inputClassification = null,
        ?string $untrustedIssueContext = null,
        ?string $untrustedIssueRetrievalContext = null,
    ): string {
        $resolvedContextHints = $this->sanitizeContextHints($contextHints ?? [
            'files' => [],
            'symbols' => [],
            'lines' => [],
        ]);

        return $this->renderer->render('prompts.commands.user', [
            'command' => $commandType->description(),
            'query' => $this->sanitizeUntrustedPromptText($query),
            'untrusted_context' => $this->sanitizeUntrustedPromptText($untrustedContext),
            'untrusted_issue_context' => $this->sanitizeUntrustedPromptText($untrustedIssueContext),
            'untrusted_issue_retrieval_context' => $this->sanitizeUntrustedPromptText($untrustedIssueRetrievalContext),
            'context_hints' => $resolvedContextHints,
            'input_classification' => $inputClassification,
        ]);
    }

    /**
     * Resolve the command-specific prompt view.
     */
    private function resolveCommandView(CommandType $commandType): string
    {
        return match ($commandType) {
            CommandType::Explain => 'prompts.commands.types.explain',
            CommandType::Analyze => 'prompts.commands.types.analyze',
            CommandType::Review => 'prompts.commands.types.review',
            CommandType::Summarize => 'prompts.commands.types.summarize',
            CommandType::Find => 'prompts.commands.types.find',
        };
    }

    /**
     * Sanitize untrusted prompt text to avoid marker injection and control characters.
     */
    private function sanitizeUntrustedPromptText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace("\r\n", "\n", $value);
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $normalized) ?? $normalized;

        $sanitized = preg_replace(
            '/<<<UNTRUSTED_(?:CONTEXT|INPUT)_(?:START|END):[a-z_]+>>>/i',
            '[REDACTED_PROMPT_MARKER]',
            $normalized
        ) ?? $normalized;

        return $this->sensitiveDataRedactor->redact($sanitized);
    }

    /**
     * Sanitize context hints before rendering into user prompt.
     *
     * @param  array{files?: array<array-key, mixed>, symbols?: array<array-key, mixed>, lines?: array<array-key, mixed>}  $contextHints
     * @return array{files: array<int, string>, symbols: array<int, string>, lines: array<int, array{start: int, end: int|null}>}
     */
    private function sanitizeContextHints(array $contextHints): array
    {
        $files = array_values(array_filter(array_map(function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            return $this->sanitizeUntrustedPromptText($value);
        }, $contextHints['files'] ?? []), static fn (?string $value): bool => is_string($value) && $value !== ''));

        $symbols = array_values(array_filter(array_map(function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            return $this->sanitizeUntrustedPromptText($value);
        }, $contextHints['symbols'] ?? []), static fn (?string $value): bool => is_string($value) && $value !== ''));

        $lines = [];

        foreach ($contextHints['lines'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $start = $line['start'] ?? null;
            $end = $line['end'] ?? null;
            if (! is_int($start)) {
                continue;
            }

            $hasInvalidEnd = ! is_int($end) && $end !== null;
            if ($hasInvalidEnd) {
                continue;
            }

            $lines[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        return [
            'files' => $files,
            'symbols' => $symbols,
            'lines' => $lines,
        ];
    }
}
