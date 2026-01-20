<?php

declare(strict_types=1);

namespace App\Services\Commands\Contracts;

use App\Models\CommandRun;

/**
 * Contract for executing commands using an AI agent with tool calling capabilities.
 */
interface CommandAgentServiceContract
{
    /**
     * Execute a command using the AI agent.
     *
     * The agent will use tools to search and read code, then synthesize
     * a response based on the gathered information.
     *
     * @return array{answer: string, tool_calls: array<int, array{name: string, arguments: array<string, mixed>, result: string}>, iterations: int, metrics: array{input_tokens: int, output_tokens: int, thinking_tokens: int, duration_ms: int, model: string, provider: string}}
     */
    public function execute(CommandRun $commandRun): array;
}
