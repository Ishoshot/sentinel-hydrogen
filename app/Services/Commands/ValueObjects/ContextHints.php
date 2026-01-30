<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

/**
 * Context hints extracted from a command query.
 */
final readonly class ContextHints
{
    /**
     * Create a new ContextHints instance.
     *
     * @param  array<string>  $files
     * @param  array<string>  $symbols
     * @param  array<LineRange>  $lines
     */
    public function __construct(
        public array $files = [],
        public array $symbols = [],
        public array $lines = [],
    ) {}

    /**
     * Create from array.
     *
     * @param  array{files?: array<string>, symbols?: array<string>, lines?: array<array{start: int, end: int|null}>}  $data
     */
    public static function fromArray(array $data): self
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = new LineRange(
                start: $line['start'],
                end: $line['end'] ?? null,
            );
        }

        return new self(
            files: $data['files'] ?? [],
            symbols: $data['symbols'] ?? [],
            lines: $lines,
        );
    }

    /**
     * Create an empty ContextHints instance.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Check if any hints were extracted.
     */
    public function hasAny(): bool
    {
        return $this->files !== [] || $this->symbols !== [] || $this->lines !== [];
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{files: array<string>, symbols: array<string>, lines: array<array{start: int, end: int|null}>}
     */
    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'symbols' => $this->symbols,
            'lines' => array_map(fn (LineRange $lr): array => $lr->toArray(), $this->lines),
        ];
    }
}
