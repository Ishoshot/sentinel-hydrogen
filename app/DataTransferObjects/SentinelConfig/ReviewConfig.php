<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Enums\SentinelConfig\SentinelConfigTone;

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
            minSeverity: isset($data['min_severity']) && is_string($data['min_severity'])
                ? SentinelConfigSeverity::tryFrom($data['min_severity']) ?? SentinelConfigSeverity::Low
                : SentinelConfigSeverity::Low,
            maxFindings: is_numeric($data['max_findings'] ?? null) ? (int) $data['max_findings'] : 25,
            categories: self::parseCategories($data['categories'] ?? null),
            tone: isset($data['tone']) && is_string($data['tone'])
                ? SentinelConfigTone::tryFrom($data['tone']) ?? SentinelConfigTone::Constructive
                : SentinelConfigTone::Constructive,
            language: is_string($data['language'] ?? null) ? $data['language'] : 'en',
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
     * Parse categories from mixed data.
     */
    private static function parseCategories(mixed $data): CategoriesConfig
    {
        if (! is_array($data)) {
            return new CategoriesConfig();
        }

        /** @var array<string, mixed> $data */
        return CategoriesConfig::fromArray($data);
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

        $result = [];
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                $result[] = (string) $item;
            }
        }

        return $result;
    }
}
