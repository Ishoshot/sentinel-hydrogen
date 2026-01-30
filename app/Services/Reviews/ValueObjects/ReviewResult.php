<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Complete result from a code review execution.
 */
final readonly class ReviewResult
{
    /**
     * Create a new ReviewResult instance.
     *
     * @param  array<int, ReviewFinding>  $findings
     */
    public function __construct(
        public ReviewSummary $summary,
        public array $findings,
        public ReviewMetrics $metrics,
        public ?PromptSnapshot $promptSnapshot = null,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{summary: array{overview: string, verdict: string, risk_level: string, strengths?: array<int, string>, concerns?: array<int, string>, recommendations?: array<int, string>}, findings: array<int, array<string, mixed>>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}, prompt_snapshot?: array{system: array{version: string, hash: string}, user: array{version: string, hash: string}, hash_algorithm: string}}  $data
     */
    public static function fromArray(array $data): self
    {
        $findings = array_map(
            ReviewFinding::fromArray(...),
            $data['findings']
        );

        $promptSnapshot = isset($data['prompt_snapshot'])
            ? PromptSnapshot::fromArray($data['prompt_snapshot'])
            : null;

        return new self(
            summary: ReviewSummary::fromArray($data['summary']),
            findings: $findings,
            metrics: ReviewMetrics::fromArray($data['metrics']),
            promptSnapshot: $promptSnapshot,
        );
    }

    /**
     * Get the count of findings.
     */
    public function findingCount(): int
    {
        return count($this->findings);
    }

    /**
     * Check if the review has any findings.
     */
    public function hasFindings(): bool
    {
        return $this->findings !== [];
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{summary: array{overview: string, verdict: string, risk_level: string, strengths: array<int, string>, concerns: array<int, string>, recommendations: array<int, string>}, findings: array<int, array<string, mixed>>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}, prompt_snapshot?: array{system: array{version: string, hash: string}, user: array{version: string, hash: string}, hash_algorithm: string}}
     */
    public function toArray(): array
    {
        $result = [
            'summary' => $this->summary->toArray(),
            'findings' => array_map(fn (ReviewFinding $f): array => $f->toArray(), $this->findings),
            'metrics' => $this->metrics->toArray(),
        ];

        if ($this->promptSnapshot instanceof PromptSnapshot) {
            $result['prompt_snapshot'] = $this->promptSnapshot->toArray();
        }

        return $result;
    }
}
