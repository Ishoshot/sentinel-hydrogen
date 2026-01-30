<?php

declare(strict_types=1);

namespace App\Services\Commands\Builders;

use App\Enums\Commands\CommandType;
use App\Support\PromptRenderer;

/**
 * Builds prompts for command execution.
 */
final readonly class CommandPromptBuilder
{
    public const string SYSTEM_PROMPT_VERSION = 'command-system@3';

    public const string USER_PROMPT_VERSION = 'command-user@2';

    /**
     * Create a new CommandPromptBuilder instance.
     */
    public function __construct(private PromptRenderer $renderer) {}

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
     */
    public function buildUserMessage(
        CommandType $commandType,
        string $query,
        ?string $untrustedContext = null,
        ?array $contextHints = null
    ): string {
        return $this->renderer->render('prompts.commands.user', [
            'command' => $commandType->description(),
            'query' => $query,
            'untrusted_context' => $untrustedContext,
            'context_hints' => $contextHints ?? [
                'files' => [],
                'symbols' => [],
                'lines' => [],
            ],
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
}
