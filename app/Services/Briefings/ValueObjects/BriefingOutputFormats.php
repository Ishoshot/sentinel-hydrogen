<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Enums\Briefings\BriefingOutputFormat;

final readonly class BriefingOutputFormats
{
    /**
     * Create a set of output formats.
     *
     * @param  array<int, BriefingOutputFormat>  $formats
     */
    public function __construct(
        public array $formats = [],
    ) {}

    /**
     * Create a set from raw string values.
     *
     * @param  array<int, string>|null  $formats
     */
    public static function fromArray(?array $formats): self
    {
        if ($formats === null) {
            return new self();
        }

        return self::fromStrings($formats);
    }

    /**
     * Normalize string formats into enum values.
     *
     * @param  array<int, string>  $formats
     */
    public static function fromStrings(array $formats): self
    {
        $resolved = [];
        $seen = [];

        foreach ($formats as $format) {
            $enum = BriefingOutputFormat::tryFrom((string) $format);
            if ($enum !== null && ! isset($seen[$enum->value])) {
                $seen[$enum->value] = true;
                $resolved[] = $enum;
            }
        }

        return new self($resolved);
    }

    /**
     * Get the default output format set.
     */
    public static function defaults(): self
    {
        return new self([
            BriefingOutputFormat::Html,
            BriefingOutputFormat::Pdf,
            BriefingOutputFormat::Markdown,
        ]);
    }

    /**
     * Determine if no formats were selected.
     */
    public function isEmpty(): bool
    {
        return $this->formats === [];
    }

    /**
     * Check whether a format is included.
     */
    public function includes(BriefingOutputFormat $format): bool
    {
        return in_array($format, $this->formats, true);
    }

    /**
     * Get the formats as enum values.
     *
     * @return array<int, BriefingOutputFormat>
     */
    public function enums(): array
    {
        return $this->formats;
    }

    /**
     * Convert the formats to string values.
     *
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (BriefingOutputFormat $format): string => $format->value,
            $this->formats,
        );
    }
}
