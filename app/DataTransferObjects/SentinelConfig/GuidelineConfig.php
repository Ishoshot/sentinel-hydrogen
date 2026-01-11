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
            path: (string) ($data['path'] ?? ''), // @phpstan-ignore cast.string
            description: isset($data['description']) ? (string) $data['description'] : null, // @phpstan-ignore cast.string
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
