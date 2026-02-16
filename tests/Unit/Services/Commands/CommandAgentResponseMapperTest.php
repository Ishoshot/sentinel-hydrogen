<?php

declare(strict_types=1);

use App\Services\Commands\Mappers\CommandAgentResponseMapper;
use App\Services\Commands\ValueObjects\ToolCall;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall as PrismToolCall;
use Prism\Prism\ValueObjects\ToolResult as PrismToolResult;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function (): void {
    $this->mapper = new CommandAgentResponseMapper();
});

function createStep(array $toolCalls = [], array $toolResults = []): Step
{
    return new Step(
        text: '',
        finishReason: FinishReason::ToolCalls,
        toolCalls: $toolCalls,
        toolResults: $toolResults,
        providerToolCalls: [],
        usage: new Usage(promptTokens: 0, completionTokens: 0),
        meta: new Meta(id: 'test', model: 'test-model'),
        messages: [],
        systemPrompts: [],
    );
}

function createTextResponse(
    array $steps = [],
    array $toolResults = [],
    ?Usage $usage = null,
    ?Meta $meta = null,
): TextResponse {
    return new TextResponse(
        steps: collect($steps),
        text: 'test response text',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: $toolResults,
        usage: $usage ?? new Usage(promptTokens: 100, completionTokens: 50),
        meta: $meta ?? new Meta(id: 'resp-123', model: 'claude-3-opus'),
        messages: collect(),
    );
}

describe('mapToolCalls', function (): void {
    it('returns empty array when no steps or tool calls', function (): void {
        $response = createTextResponse();

        $result = $this->mapper->mapToolCalls($response);

        expect($result)->toBe([]);
    });

    it('maps tool calls from a single step', function (): void {
        $prismToolCall = new PrismToolCall(
            id: 'tc-1',
            name: 'search_code',
            arguments: ['query' => 'auth middleware', 'path' => 'src/'],
        );

        $step = createStep(toolCalls: [$prismToolCall]);
        $response = createTextResponse(steps: [$step]);

        $result = $this->mapper->mapToolCalls($response);

        expect($result)->toHaveCount(1)
            ->and($result[0])->toBeInstanceOf(ToolCall::class)
            ->and($result[0]->name)->toBe('search_code')
            ->and($result[0]->arguments)->toBe(['query' => 'auth middleware', 'path' => 'src/'])
            ->and($result[0]->result)->toBe('');
    });

    it('maps tool calls from multiple steps', function (): void {
        $toolCall1 = new PrismToolCall(id: 'tc-1', name: 'read_file', arguments: ['path' => 'a.php']);
        $toolCall2 = new PrismToolCall(id: 'tc-2', name: 'write_file', arguments: ['path' => 'b.php']);

        $step1 = createStep(toolCalls: [$toolCall1]);
        $step2 = createStep(toolCalls: [$toolCall2]);

        $response = createTextResponse(steps: [$step1, $step2]);

        $result = $this->mapper->mapToolCalls($response);

        expect($result)->toHaveCount(2)
            ->and($result[0]->name)->toBe('read_file')
            ->and($result[1]->name)->toBe('write_file');
    });

    it('maps tool results to corresponding tool calls', function (): void {
        $toolCall1 = new PrismToolCall(id: 'tc-1', name: 'read_file', arguments: ['path' => 'a.php']);
        $toolCall2 = new PrismToolCall(id: 'tc-2', name: 'search', arguments: ['q' => 'test']);

        $step = createStep(toolCalls: [$toolCall1, $toolCall2]);

        $toolResult1 = new PrismToolResult(
            toolCallId: 'tc-1',
            toolName: 'read_file',
            args: ['path' => 'a.php'],
            result: 'file contents here',
        );

        $toolResult2 = new PrismToolResult(
            toolCallId: 'tc-2',
            toolName: 'search',
            args: ['q' => 'test'],
            result: ['matches' => ['file1.php', 'file2.php']],
        );

        $response = createTextResponse(steps: [$step], toolResults: [$toolResult1, $toolResult2]);

        $result = $this->mapper->mapToolCalls($response);

        expect($result)->toHaveCount(2)
            ->and($result[0]->result)->toBe('file contents here')
            ->and($result[1]->result)->toBe('{"matches":["file1.php","file2.php"]}');
    });

    it('handles tool results with null result value', function (): void {
        $toolCall = new PrismToolCall(id: 'tc-1', name: 'delete_file', arguments: ['path' => 'tmp.php']);
        $step = createStep(toolCalls: [$toolCall]);

        $toolResult = new PrismToolResult(
            toolCallId: 'tc-1',
            toolName: 'delete_file',
            args: ['path' => 'tmp.php'],
            result: null,
        );

        $response = createTextResponse(steps: [$step], toolResults: [$toolResult]);

        $result = $this->mapper->mapToolCalls($response);

        expect($result[0]->result)->toBe('');
    });

    it('skips tool results that exceed tool call count', function (): void {
        $toolCall = new PrismToolCall(id: 'tc-1', name: 'read_file', arguments: []);
        $step = createStep(toolCalls: [$toolCall]);

        $toolResult1 = new PrismToolResult(
            toolCallId: 'tc-1',
            toolName: 'read_file',
            args: [],
            result: 'matched',
        );

        $extraResult = new PrismToolResult(
            toolCallId: 'tc-999',
            toolName: 'extra',
            args: [],
            result: 'should be ignored',
        );

        $response = createTextResponse(steps: [$step], toolResults: [$toolResult1, $extraResult]);

        $result = $this->mapper->mapToolCalls($response);

        expect($result)->toHaveCount(1)
            ->and($result[0]->result)->toBe('matched');
    });
});

describe('extractUsageMetrics', function (): void {
    it('extracts basic usage metrics', function (): void {
        $usage = new Usage(promptTokens: 500, completionTokens: 200);
        $meta = new Meta(id: 'resp-1', model: 'claude-3-opus');

        $response = createTextResponse(usage: $usage, meta: $meta);

        $result = $this->mapper->extractUsageMetrics($response);

        expect($result['input_tokens'])->toBe(500)
            ->and($result['output_tokens'])->toBe(200)
            ->and($result['thinking_tokens'])->toBe(0)
            ->and($result['cache_creation_input_tokens'])->toBe(0)
            ->and($result['cache_read_input_tokens'])->toBe(0);
    });

    it('extracts cache tokens from usage', function (): void {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 300,
            cacheWriteInputTokens: 150,
            cacheReadInputTokens: 75,
        );
        $meta = new Meta(id: 'resp-2', model: 'claude-3-opus');

        $response = createTextResponse(usage: $usage, meta: $meta);

        $result = $this->mapper->extractUsageMetrics($response);

        expect($result['input_tokens'])->toBe(1000)
            ->and($result['output_tokens'])->toBe(300)
            ->and($result['cache_creation_input_tokens'])->toBe(150)
            ->and($result['cache_read_input_tokens'])->toBe(75);
    });

    it('extracts thinking tokens from usage thoughtTokens', function (): void {
        $usage = new Usage(
            promptTokens: 800,
            completionTokens: 400,
            thoughtTokens: 120,
        );
        $meta = new Meta(id: 'resp-3', model: 'claude-3-opus');

        $response = createTextResponse(usage: $usage, meta: $meta);

        $result = $this->mapper->extractUsageMetrics($response);

        expect($result['thinking_tokens'])->toBe(120);
    });

    it('returns zero for all optional metrics when not present', function (): void {
        $usage = new Usage(promptTokens: 50, completionTokens: 25);
        $meta = new Meta(id: 'resp-4', model: 'test-model');

        $response = createTextResponse(usage: $usage, meta: $meta);

        $result = $this->mapper->extractUsageMetrics($response);

        expect($result)->toBe([
            'input_tokens' => 50,
            'output_tokens' => 25,
            'thinking_tokens' => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ]);
    });
});
