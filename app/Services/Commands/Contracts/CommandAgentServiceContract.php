<?php

declare(strict_types=1);

namespace App\Services\Commands\Contracts;

use App\Models\CommandRun;
use App\Services\Commands\ValueObjects\CommandExecutionResult;

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
     */
    public function execute(CommandRun $commandRun): CommandExecutionResult;
}
