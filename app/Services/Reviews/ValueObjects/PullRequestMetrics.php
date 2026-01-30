<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Metrics about a pull request (file changes, lines added/deleted).
 */
final readonly class PullRequestMetrics
{
    /**
     * Create a new PullRequestMetrics instance.
     */
    public function __construct(
        public int $filesChanged,
        public int $linesAdded,
        public int $linesDeleted,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{files_changed: int, lines_added: int, lines_deleted: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filesChanged: $data['files_changed'],
            linesAdded: $data['lines_added'],
            linesDeleted: $data['lines_deleted'],
        );
    }

    /**
     * Get the total lines changed.
     */
    public function totalLinesChanged(): int
    {
        return $this->linesAdded + $this->linesDeleted;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{files_changed: int, lines_added: int, lines_deleted: int}
     */
    public function toArray(): array
    {
        return [
            'files_changed' => $this->filesChanged,
            'lines_added' => $this->linesAdded,
            'lines_deleted' => $this->linesDeleted,
        ];
    }
}
