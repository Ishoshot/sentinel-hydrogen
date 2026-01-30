<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

/**
 * Represents a single tool call made by the AI agent.
 */
final readonly class ToolCall
{
    /**
     * Create a new ToolCall instance.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public string $result,
    ) {}

    /**
     * Create a ToolCall from an array.
     *
     * @param  array{name: string, arguments: array<string, mixed>, result: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            arguments: $data['arguments'],
            result: $data['result'],
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{name: string, arguments: array<string, mixed>, result: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
            'result' => $this->result,
        ];
    }
}
