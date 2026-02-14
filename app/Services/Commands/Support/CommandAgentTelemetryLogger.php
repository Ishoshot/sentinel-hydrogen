<?php

declare(strict_types=1);

namespace App\Services\Commands\Support;

use App\Models\CommandRun;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CommandAgentTelemetryLogger
{
    /**
     * Log execution start.
     */
    public function logStarted(CommandRun $commandRun, CommandAgentExecutionContext $context): void
    {
        Log::debug('Starting command agent execution', [
            'command_run_id' => $commandRun->id,
            'command_type' => $commandRun->command_type->value,
            'provider' => $context->provider->value,
            'model' => $context->model,
            'thinking_enabled' => $context->thinkingEnabled,
        ]);
    }

    /**
     * Log execution failure.
     */
    public function logFailed(CommandRun $commandRun, Throwable $throwable): void
    {
        Log::error('Command agent execution failed', [
            'command_run_id' => $commandRun->id,
            'error' => $throwable->getMessage(),
        ]);
    }

    /**
     * Log execution completion.
     */
    public function logCompleted(CommandRun $commandRun, int $iterations, int $toolCallsCount, int $durationMs): void
    {
        Log::info('Command agent execution completed', [
            'command_run_id' => $commandRun->id,
            'iterations' => $iterations,
            'tool_calls' => $toolCallsCount,
            'duration_ms' => $durationMs,
        ]);
    }
}
