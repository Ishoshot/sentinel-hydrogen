<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

use App\Enums\Commands\CommandType;

/**
 * Result of parsing an @sentinel command from a comment.
 */
final readonly class ParsedCommand
{
    /**
     * Create a new ParsedCommand instance.
     */
    public function __construct(
        public bool $found,
        public ?CommandType $commandType = null,
        public ?string $query = null,
        public ?ContextHints $contextHints = null,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{found: bool, command_type: CommandType|null, query: string|null, context_hints: array{files: array<string>, symbols: array<string>, lines: array<array{start: int, end: int|null}>}}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            found: $data['found'],
            commandType: $data['command_type'],
            query: $data['query'],
            contextHints: ContextHints::fromArray($data['context_hints']),
        );
    }

    /**
     * Create a "not found" result.
     */
    public static function notFound(): self
    {
        return new self(found: false);
    }

    /**
     * Create a found result with command details.
     */
    public static function found(CommandType $commandType, string $query, ContextHints $contextHints): self
    {
        return new self(
            found: true,
            commandType: $commandType,
            query: $query,
            contextHints: $contextHints,
        );
    }

    /**
     * Check if a command was found.
     */
    public function wasFound(): bool
    {
        return $this->found;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{found: bool, command_type: CommandType|null, query: string|null, context_hints: array{files: array<string>, symbols: array<string>, lines: array<array{start: int, end: int|null}>}|null}|null
     */
    public function toArray(): ?array
    {
        if (! $this->found) {
            return null;
        }

        return [
            'found' => $this->found,
            'command_type' => $this->commandType,
            'query' => $this->query,
            'context_hints' => $this->contextHints?->toArray(),
        ];
    }
}
