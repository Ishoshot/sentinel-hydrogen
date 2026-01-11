<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

use App\Enums\SentinelConfigSeverity;
use App\Enums\SentinelConfigTone;

/**
 * Configuration for review behavior and focus areas.
 */
final readonly class ReviewConfig
{
    /**
     * Create a new ReviewConfig instance.
     *
     * @param  SentinelConfigSeverity  $minSeverity  Minimum severity to report
     * @param  int  $maxFindings  Maximum findings to report per run
     * @param  CategoriesConfig  $categories  Which categories to analyze
     * @param  SentinelConfigTone  $tone  Review feedback tone
     * @param  string  $language  Response language (ISO 639-1 code)
     * @param  array<int, string>  $focus  Custom focus areas for the review
     */
    public function __construct(
        public SentinelConfigSeverity $minSeverity = SentinelConfigSeverity::Low,
        public int $maxFindings = 25,
        public CategoriesConfig $categories = new CategoriesConfig(),
        public SentinelConfigTone $tone = SentinelConfigTone::Constructive,
        public string $language = 'en',
        public array $focus = [],
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minSeverity: isset($data['min_severity'])
                ? SentinelConfigSeverity::from((string) $data['min_severity']) // @phpstan-ignore cast.string
                : SentinelConfigSeverity::Low,
            maxFindings: (int) ($data['max_findings'] ?? 25), // @phpstan-ignore cast.int
            categories: isset($data['categories']) && is_array($data['categories'])
                ? CategoriesConfig::fromArray($data['categories']) // @phpstan-ignore argument.type
                : new CategoriesConfig(),
            tone: isset($data['tone'])
                ? SentinelConfigTone::from((string) $data['tone']) // @phpstan-ignore cast.string
                : SentinelConfigTone::Constructive,
            language: (string) ($data['language'] ?? 'en'), // @phpstan-ignore cast.string
            focus: self::toStringArray($data['focus'] ?? []),
        );
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'min_severity' => $this->minSeverity->value,
            'max_findings' => $this->maxFindings,
            'categories' => $this->categories->toArray(),
            'tone' => $this->tone->value,
            'language' => $this->language,
            'focus' => $this->focus,
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
