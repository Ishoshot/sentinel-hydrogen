<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

/**
 * Configuration for file path filtering.
 */
final readonly class PathsConfig
{
    /**
     * Create a new PathsConfig instance.
     *
     * @param  array<int, string>  $ignore  Glob patterns for files to ignore
     * @param  array<int, string>  $include  Glob patterns for files to include (empty = all)
     * @param  array<int, string>  $sensitive  Glob patterns for sensitive files (higher scrutiny)
     */
    public function __construct(
        public array $ignore = [],
        public array $include = [],
        public array $sensitive = [],
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ignore: self::toStringArray($data['ignore'] ?? []),
            include: self::toStringArray($data['include'] ?? []),
            sensitive: self::toStringArray($data['sensitive'] ?? []),
        );
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self(
            ignore: ['*.lock', 'vendor/**', 'node_modules/**'],
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
            'ignore' => $this->ignore,
            'include' => $this->include,
            'sensitive' => $this->sensitive,
        ];
    }

    /**
     * Convert mixed array to string array.
     *
     * @return array<int, string>
     */
    private static function toStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $item): string => (string) $item, // @phpstan-ignore cast.string
            $value
        ));
    }
}
