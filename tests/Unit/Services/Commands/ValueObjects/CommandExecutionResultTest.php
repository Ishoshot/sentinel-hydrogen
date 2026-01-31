<?php

declare(strict_types=1);

use App\Services\Commands\ValueObjects\CommandExecutionResult;
use App\Services\Commands\ValueObjects\ExecutionMetrics;
use App\Services\Commands\ValueObjects\PullRequestMetadata;
use App\Services\Commands\ValueObjects\ToolCall;

it('can be constructed with all parameters', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );
    $toolCall = new ToolCall(
        name: 'read_file',
        arguments: ['path' => 'test.php'],
        result: 'file content',
    );
    $prMetadata = new PullRequestMetadata(
        prTitle: 'Test PR',
        prContextIncluded: true,
    );

    $result = new CommandExecutionResult(
        answer: 'The answer is 42',
        toolCalls: [$toolCall],
        iterations: 3,
        metrics: $metrics,
        prMetadata: $prMetadata,
    );

    expect($result->answer)->toBe('The answer is 42');
    expect($result->toolCalls)->toHaveCount(1);
    expect($result->iterations)->toBe(3);
    expect($result->metrics)->toBe($metrics);
    expect($result->prMetadata)->toBe($prMetadata);
});

it('can be constructed without pr metadata', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );

    $result = new CommandExecutionResult(
        answer: 'Answer',
        toolCalls: [],
        iterations: 1,
        metrics: $metrics,
    );

    expect($result->prMetadata)->toBeNull();
});

it('creates from array with all fields', function (): void {
    $result = CommandExecutionResult::fromArray([
        'answer' => 'The result',
        'tool_calls' => [
            ['name' => 'search', 'arguments' => ['query' => 'test'], 'result' => 'found'],
        ],
        'iterations' => 2,
        'metrics' => [
            'input_tokens' => 200,
            'output_tokens' => 100,
            'thinking_tokens' => 50,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'duration_ms' => 2000,
            'model' => 'gpt-4',
            'provider' => 'openai',
        ],
        'pr_metadata' => [
            'pr_title' => 'Fix bug',
            'pr_context_included' => true,
        ],
    ]);

    expect($result->answer)->toBe('The result');
    expect($result->toolCalls)->toHaveCount(1);
    expect($result->iterations)->toBe(2);
    expect($result->metrics->inputTokens)->toBe(200);
    expect($result->prMetadata)->not->toBeNull();
    expect($result->prMetadata->prTitle)->toBe('Fix bug');
});

it('counts tool calls', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );
    $toolCalls = [
        new ToolCall('tool1', [], 'result1'),
        new ToolCall('tool2', [], 'result2'),
        new ToolCall('tool3', [], 'result3'),
    ];

    $result = new CommandExecutionResult(
        answer: 'Answer',
        toolCalls: $toolCalls,
        iterations: 1,
        metrics: $metrics,
    );

    expect($result->toolCallCount())->toBe(3);
});

it('checks if has pr context when included', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );
    $prMetadata = new PullRequestMetadata(prContextIncluded: true);

    $result = new CommandExecutionResult(
        answer: 'Answer',
        toolCalls: [],
        iterations: 1,
        metrics: $metrics,
        prMetadata: $prMetadata,
    );

    expect($result->hasPrContext())->toBeTrue();
});

it('checks if has pr context when not included', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );
    $prMetadata = new PullRequestMetadata(prContextIncluded: false);

    $result = new CommandExecutionResult(
        answer: 'Answer',
        toolCalls: [],
        iterations: 1,
        metrics: $metrics,
        prMetadata: $prMetadata,
    );

    expect($result->hasPrContext())->toBeFalse();
});

it('checks if has pr context when null', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );

    $result = new CommandExecutionResult(
        answer: 'Answer',
        toolCalls: [],
        iterations: 1,
        metrics: $metrics,
    );

    expect($result->hasPrContext())->toBeFalse();
});

it('converts to array with all fields', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );
    $toolCall = new ToolCall(
        name: 'read_file',
        arguments: ['path' => 'test.php'],
        result: 'file content',
    );
    $prMetadata = new PullRequestMetadata(
        prTitle: 'Test PR',
        prContextIncluded: true,
    );

    $result = new CommandExecutionResult(
        answer: 'The answer',
        toolCalls: [$toolCall],
        iterations: 2,
        metrics: $metrics,
        prMetadata: $prMetadata,
    );

    $array = $result->toArray();

    expect($array['answer'])->toBe('The answer');
    expect($array['tool_calls'])->toHaveCount(1);
    expect($array['iterations'])->toBe(2);
    expect($array['metrics'])->toBeArray();
    expect($array['pr_metadata'])->toBeArray();
});

it('converts to array with null pr metadata', function (): void {
    $metrics = new ExecutionMetrics(
        inputTokens: 100,
        outputTokens: 50,
        thinkingTokens: 0,
        cacheCreationInputTokens: 0,
        cacheReadInputTokens: 0,
        durationMs: 1000,
        model: 'claude-3',
        provider: 'anthropic',
    );

    $result = new CommandExecutionResult(
        answer: 'Answer',
        toolCalls: [],
        iterations: 1,
        metrics: $metrics,
    );

    $array = $result->toArray();

    expect($array['pr_metadata'])->toBeNull();
});
