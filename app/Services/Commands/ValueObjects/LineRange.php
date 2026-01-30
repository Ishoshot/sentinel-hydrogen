<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

/**
 * Represents a range of lines in a file.
 */
final readonly class LineRange
{
    /**
     * Create a new LineRange instance.
     */
    public function __construct(
        public int $start,
        public ?int $end = null,
    ) {}

    /**
     * Check if this is a single line reference.
     */
    public function isSingleLine(): bool
    {
        return $this->end === null || $this->end === $this->start;
    }

    /**
     * Get the number of lines in the range.
     */
    public function lineCount(): int
    {
        if ($this->end === null) {
            return 1;
        }

        return max(1, $this->end - $this->start + 1);
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{start: int, end: int|null}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
