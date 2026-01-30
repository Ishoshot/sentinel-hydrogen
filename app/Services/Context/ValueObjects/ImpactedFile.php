<?php

declare(strict_types=1);

namespace App\Services\Context\ValueObjects;

/**
 * Represents a file that references symbols modified in the current PR.
 *
 * Used by ImpactAnalysisCollector to track cross-reference impacts.
 */
final readonly class ImpactedFile
{
    /**
     * Create a new ImpactedFile instance.
     *
     * @param  string  $filePath  Path to the impacted file
     * @param  string  $content  Relevant content from the file
     * @param  string  $matchedSymbol  The symbol being referenced
     * @param  string  $matchType  Type of reference (function_call, class_instantiation, method_call, extends, implements)
     * @param  float  $score  Relevance score (0.0-1.0)
     * @param  int  $matchCount  Number of times the symbol is referenced in this file
     */
    public function __construct(
        public string $filePath,
        public string $content,
        public string $matchedSymbol,
        public string $matchType,
        public float $score,
        public int $matchCount,
    ) {}

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $scoreValue = $data['score'] ?? 0.0;

        if (! is_numeric($scoreValue)) {
            $scoreValue = 0.0;
        }

        return new self(
            filePath: (string) ($data['file_path'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            matchedSymbol: (string) ($data['matched_symbol'] ?? ''),
            matchType: (string) ($data['match_type'] ?? 'unknown'),
            score: (float) $scoreValue,
            matchCount: (int) ($data['match_count'] ?? 1),
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'content' => $this->content,
            'matched_symbol' => $this->matchedSymbol,
            'match_type' => $this->matchType,
            'score' => $this->score,
            'match_count' => $this->matchCount,
            'reason' => $this->getReason(),
        ];
    }

    /**
     * Get a human-readable reason for why this file is impacted.
     */
    public function getReason(): string
    {
        return match ($this->matchType) {
            'function_call' => sprintf('Calls function `%s()`', $this->matchedSymbol),
            'class_instantiation' => sprintf('Instantiates class `%s`', $this->matchedSymbol),
            'method_call' => sprintf('Calls method `%s()`', $this->matchedSymbol),
            'extends' => sprintf('Extends class `%s`', $this->matchedSymbol),
            'implements' => sprintf('Implements interface `%s`', $this->matchedSymbol),
            default => sprintf('References `%s`', $this->matchedSymbol),
        };
    }
}
