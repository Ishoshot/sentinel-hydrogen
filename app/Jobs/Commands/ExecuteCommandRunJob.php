<?php

declare(strict_types=1);

namespace App\Jobs\Commands;

use App\Actions\Commands\ExecuteCommandRun;
use App\Enums\Queue;
use App\Models\CommandRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to execute a command run asynchronously.
 */
final class ExecuteCommandRunJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $commandRunId)
    {
        $this->onQueue(Queue::Commands->value);
    }

    /**
     * Execute the job.
     */
    public function handle(ExecuteCommandRun $executeCommandRun): void
    {
        $commandRun = CommandRun::find($this->commandRunId);

        if ($commandRun === null) {
            Log::warning('Command run not found', [
                'command_run_id' => $this->commandRunId,
            ]);

            return;
        }

        Log::info('Executing command run', [
            'command_run_id' => $commandRun->id,
            'command_type' => $commandRun->command_type->value,
            'repository' => $commandRun->repository?->full_name,
        ]);

        $executeCommandRun->handle($commandRun);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Command run job failed', [
            'command_run_id' => $this->commandRunId,
            'error' => $exception->getMessage(),
        ]);
    }
}
