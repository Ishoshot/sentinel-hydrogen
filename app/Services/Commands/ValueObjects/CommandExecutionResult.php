<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

/**
 * Result of a command execution by the AI agent.
 */
final readonly class CommandExecutionResult
{
    /**
     * Create a new CommandExecutionResult instance.
     *
     * @param  array<int, ToolCall>  $toolCalls
     */
    public function __construct(
        public string $answer,
        public array $toolCalls,
        public int $iterations,
        public ExecutionMetrics $metrics,
        public ?PullRequestMetadata $prMetadata = null,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{answer: string, tool_calls: array<int, array{name: string, arguments: array<string, mixed>, result: string}>, iterations: int, metrics: array{input_tokens: int, output_tokens: int, thinking_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int, duration_ms: int, model: string, provider: string}, pr_metadata: array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool, base_branch?: string, head_branch?: string}|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $toolCalls = array_map(
            ToolCall::fromArray(...),
            $data['tool_calls']
        );

        return new self(
            answer: $data['answer'],
            toolCalls: $toolCalls,
            iterations: $data['iterations'],
            metrics: ExecutionMetrics::fromArray($data['metrics']),
            prMetadata: PullRequestMetadata::fromArray($data['pr_metadata']),
        );
    }

    /**
     * Get the number of tool calls made.
     */
    public function toolCallCount(): int
    {
        return count($this->toolCalls);
    }

    /**
     * Check if PR context was included.
     */
    public function hasPrContext(): bool
    {
        return $this->prMetadata?->hasContext() === true;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{answer: string, tool_calls: array<int, array{name: string, arguments: array<string, mixed>, result: string}>, iterations: int, metrics: array{input_tokens: int, output_tokens: int, thinking_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, duration_ms: int, model: string, provider: string}, pr_metadata: array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool, base_branch?: string, head_branch?: string}|null}
     */
    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'tool_calls' => array_map(fn (ToolCall $tc): array => $tc->toArray(), $this->toolCalls),
            'iterations' => $this->iterations,
            'metrics' => $this->metrics->toArray(),
            'pr_metadata' => $this->prMetadata?->toArray(),
        ];
    }
}
