<?php

declare(strict_types=1);

namespace App\Actions\Commands;

use App\Enums\Commands\CommandRunStatus;
use App\Models\CommandRun;
use App\Services\Commands\CommandToolResultSanitizer;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes a command run using the AI agent.
 */
final readonly class ExecuteCommandRun
{
    /**
     * Create a new ExecuteCommandRun instance.
     */
    public function __construct(
        private CommandAgentServiceContract $agentService,
        private PostCommandResponse $postResponse,
        private CommandToolResultSanitizer $toolResultSanitizer,
    ) {}

    /**
     * Execute the command run.
     */
    public function handle(CommandRun $commandRun): void
    {
        $ctx = [
            'command_run_id' => $commandRun->id,
            'command_type' => $commandRun->command_type->value,
            'repository' => $commandRun->repository?->full_name,
        ];

        $commandRun->update([
            'status' => CommandRunStatus::InProgress,
            'started_at' => now(),
        ]);

        try {
            $result = $this->agentService->execute($commandRun);

            $toolCallArrays = array_map(fn (\App\Services\Commands\ValueObjects\ToolCall $tc): array => $tc->toArray(), $result->toolCalls);
            $sanitizedToolCalls = $this->toolResultSanitizer->sanitizeToolCalls($toolCallArrays);

            $commandRun->update([
                'status' => CommandRunStatus::Completed,
                'completed_at' => now(),
                'duration_seconds' => $this->calculateDuration($commandRun),
                'response' => [
                    'answer' => $result->answer,
                    'tool_calls' => $sanitizedToolCalls,
                    'iterations' => $result->iterations,
                ],
                'metrics' => $result->metrics->toArray(),
                'metadata' => [
                    'tool_call_count' => count($sanitizedToolCalls),
                    'iterations' => $result->iterations,
                ],
            ]);

            Log::info('Command run completed successfully', $ctx);

            $this->postResponse->handle($commandRun, $result->answer);
        } catch (Throwable $throwable) {
            Log::error('Command run failed', [...$ctx, 'error' => $throwable->getMessage()]);

            $commandRun->update([
                'status' => CommandRunStatus::Failed,
                'completed_at' => now(),
                'duration_seconds' => $this->calculateDuration($commandRun),
                'response' => ['error' => $this->toolResultSanitizer->sanitizeErrorMessage($throwable->getMessage())],
            ]);

            $this->postResponse->handleError($commandRun, $throwable);
        }
    }

    /**
     * Calculate the duration of the command run in seconds.
     */
    private function calculateDuration(CommandRun $commandRun): ?int
    {
        if ($commandRun->started_at === null) {
            return null;
        }

        return (int) now()->diffInSeconds($commandRun->started_at);
    }
}
