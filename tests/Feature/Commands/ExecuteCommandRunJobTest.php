<?php

declare(strict_types=1);

use App\Actions\Commands\ExecuteCommandRun;
use App\Enums\Commands\CommandInputDecision;
use App\Enums\Commands\CommandInputRiskLevel;
use App\Enums\Commands\CommandRunStatus;
use App\Jobs\Commands\ExecuteCommandRunJob;
use App\Models\CommandRun;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\CommandInputClassificationServiceContract;
use App\Services\Commands\ValueObjects\CommandExecutionResult;
use App\Services\Commands\ValueObjects\CommandInputClassificationResult;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

use function Pest\Laravel\mock;

it('skips terminal command runs', function (CommandRunStatus $status): void {
    $commandRun = CommandRun::factory()->create([
        'status' => $status,
    ]);

    mock(CommandAgentServiceContract::class)->shouldNotReceive('execute');
    mock(GitHubApiServiceContract::class)->shouldNotReceive('createIssueComment');

    $job = new ExecuteCommandRunJob($commandRun->id);
    $job->handle(app(ExecuteCommandRun::class));
})->with([
    CommandRunStatus::Completed,
    CommandRunStatus::Failed,
]);

it('executes queued command runs', function (): void {
    $commandRun = CommandRun::factory()->create([
        'status' => CommandRunStatus::Queued,
    ]);

    mock(CommandAgentServiceContract::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn(CommandExecutionResult::fromArray([
            'answer' => 'Test response.',
            'tool_calls' => [],
            'iterations' => 1,
            'metrics' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
                'thinking_tokens' => 0,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
                'duration_ms' => 100,
                'model' => 'test-model',
                'provider' => 'test',
            ],
            'pr_metadata' => null,
        ]));

    mock(GitHubApiServiceContract::class)
        ->shouldReceive('createIssueComment')
        ->once();

    mock(CommandInputClassificationServiceContract::class)
        ->shouldReceive('classify')
        ->andReturn(new CommandInputClassificationResult(
            decision: CommandInputDecision::Allow,
            riskLevel: CommandInputRiskLevel::Low,
            riskTypes: [],
            confidence: 0.0,
            summary: 'No elevated risk patterns detected.',
            signals: [],
        ));

    $job = new ExecuteCommandRunJob($commandRun->id);
    $job->handle(app(ExecuteCommandRun::class));

    $commandRun->refresh();
    expect($commandRun->status)->toBe(CommandRunStatus::Completed);
});
