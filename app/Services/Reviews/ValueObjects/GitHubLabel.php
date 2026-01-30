<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Represents a GitHub label.
 */
final readonly class GitHubLabel
{
    /**
     * Create a new GitHubLabel instance.
     */
    public function __construct(
        public string $name,
        public string $color = 'cccccc',
    ) {}

    /**
     * Create from array.
     *
     * @param  array{name: string, color?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            color: $data['color'] ?? 'cccccc',
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{name: string, color: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
        ];
    }
}
