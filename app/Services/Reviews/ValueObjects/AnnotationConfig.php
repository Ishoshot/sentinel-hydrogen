<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Resolved annotation posting configuration for a review run.
 */
final readonly class AnnotationConfig
{
    /**
     * Create a new annotation config instance.
     */
    public function __construct(
        public string $style,
        public string $postThreshold,
        public bool $grouped,
        public bool $includeSuggestions,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            style: $config['style'],
            postThreshold: $config['post_threshold'],
            grouped: $config['grouped'],
            includeSuggestions: $config['include_suggestions'],
        );
    }

    /**
     * Convert to an array representation.
     *
     * @return array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}
     */
    public function toArray(): array
    {
        return [
            'style' => $this->style,
            'post_threshold' => $this->postThreshold,
            'grouped' => $this->grouped,
            'include_suggestions' => $this->includeSuggestions,
        ];
    }

    /**
     * Create a new instance with the given property overrides.
     */
    public function with(
        ?string $style = null,
        ?string $postThreshold = null,
        ?bool $grouped = null,
        ?bool $includeSuggestions = null,
    ): self {
        return new self(
            style: $style ?? $this->style,
            postThreshold: $postThreshold ?? $this->postThreshold,
            grouped: $grouped ?? $this->grouped,
            includeSuggestions: $includeSuggestions ?? $this->includeSuggestions,
        );
    }
}
