<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

/**
 * Configuration for a single custom guideline.
 */
final readonly class GuidelineConfig
{
    /**
     * Create a new GuidelineConfig instance.
     */
    public function __construct(
        public string $path,
        public ?string $description = null,
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            path: is_string($data['path'] ?? null) ? $data['path'] : '',
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'description' => $this->description,
        ];
    }
}
