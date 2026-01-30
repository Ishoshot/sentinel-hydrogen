<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;

/**
 * A single finding from a code review.
 */
final readonly class ReviewFinding
{
    /**
     * Create a new ReviewFinding instance.
     *
     * @param  array<int, string>  $references
     */
    public function __construct(
        public SentinelConfigSeverity $severity,
        public FindingCategory $category,
        public string $title,
        public string $description,
        public string $impact,
        public float $confidence,
        public ?string $filePath = null,
        public ?int $lineStart = null,
        public ?int $lineEnd = null,
        public ?string $currentCode = null,
        public ?string $replacementCode = null,
        public ?string $explanation = null,
        public array $references = [],
    ) {}

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            severity: SentinelConfigSeverity::tryFrom($data['severity']) ?? SentinelConfigSeverity::Info,
            category: FindingCategory::tryFrom($data['category']) ?? FindingCategory::Maintainability,
            title: $data['title'],
            description: $data['description'],
            impact: $data['impact'] ?? '',
            confidence: $data['confidence'],
            filePath: $data['file_path'] ?? null,
            lineStart: $data['line_start'] ?? null,
            lineEnd: $data['line_end'] ?? null,
            currentCode: $data['current_code'] ?? null,
            replacementCode: $data['replacement_code'] ?? null,
            explanation: $data['explanation'] ?? null,
            references: $data['references'] ?? [],
        );
    }

    /**
     * Check if this finding has a location.
     */
    public function hasLocation(): bool
    {
        return $this->filePath !== null;
    }

    /**
     * Check if this finding has a code suggestion.
     */
    public function hasSuggestion(): bool
    {
        return $this->currentCode !== null && $this->replacementCode !== null;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'severity' => $this->severity->value,
            'category' => $this->category->value,
            'title' => $this->title,
            'description' => $this->description,
            'impact' => $this->impact,
            'confidence' => $this->confidence,
        ];

        if ($this->filePath !== null) {
            $result['file_path'] = $this->filePath;
        }

        if ($this->lineStart !== null) {
            $result['line_start'] = $this->lineStart;
        }

        if ($this->lineEnd !== null) {
            $result['line_end'] = $this->lineEnd;
        }

        if ($this->currentCode !== null) {
            $result['current_code'] = $this->currentCode;
        }

        if ($this->replacementCode !== null) {
            $result['replacement_code'] = $this->replacementCode;
        }

        if ($this->explanation !== null) {
            $result['explanation'] = $this->explanation;
        }

        if ($this->references !== []) {
            $result['references'] = $this->references;
        }

        return $result;
    }
}
