<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Represents a file in a pull request.
 */
final readonly class PullRequestFile
{
    /**
     * Create a new PullRequestFile instance.
     */
    public function __construct(
        public string $filename,
        public int $additions,
        public int $deletions,
        public int $changes,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{filename: string, additions: int, deletions: int, changes: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filename: $data['filename'],
            additions: $data['additions'],
            deletions: $data['deletions'],
            changes: $data['changes'],
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{filename: string, additions: int, deletions: int, changes: int}
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'additions' => $this->additions,
            'deletions' => $this->deletions,
            'changes' => $this->changes,
        ];
    }
}
