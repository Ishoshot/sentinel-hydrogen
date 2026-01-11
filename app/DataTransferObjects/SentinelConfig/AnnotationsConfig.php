<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

use App\Enums\AnnotationStyle;
use App\Enums\SentinelConfigSeverity;

/**
 * Configuration for how annotations are posted.
 */
final readonly class AnnotationsConfig
{
    /**
     * Create a new AnnotationsConfig instance.
     */
    public function __construct(
        public AnnotationStyle $style = AnnotationStyle::Review,
        public SentinelConfigSeverity $postThreshold = SentinelConfigSeverity::Medium,
        public bool $grouped = true,
        public bool $includeSuggestions = true,
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            style: isset($data['style'])
                ? AnnotationStyle::from((string) $data['style']) // @phpstan-ignore cast.string
                : AnnotationStyle::Review,
            postThreshold: isset($data['post_threshold'])
                ? SentinelConfigSeverity::from((string) $data['post_threshold']) // @phpstan-ignore cast.string
                : SentinelConfigSeverity::Medium,
            grouped: (bool) ($data['grouped'] ?? true),
            includeSuggestions: (bool) ($data['include_suggestions'] ?? true),
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
            'style' => $this->style->value,
            'post_threshold' => $this->postThreshold->value,
            'grouped' => $this->grouped,
            'include_suggestions' => $this->includeSuggestions,
        ];
    }
}
