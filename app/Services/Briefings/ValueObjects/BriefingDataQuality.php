<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * Represents data quality signals for a briefing.
 */
final readonly class BriefingDataQuality
{
    /**
     * @param  array<int, string>  $notes
     */
    public function __construct(
        public bool $isSparse,
        public int $totalRuns,
        public int $activeDays,
        public int $periodDays,
        public float $reviewCoverage,
        public array $notes = [],
    ) {}

    /**
     * Create a data quality instance from a payload array.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $notes = $payload['notes'] ?? [];
        $reviewCoverage = $payload['review_coverage'] ?? 0.0;

        if (! is_array($notes)) {
            $notes = [];
        }

        $normalizedNotes = array_values(
            array_map(static fn (mixed $note): string => (string) $note, $notes)
        );

        $normalizedReviewCoverage = is_numeric($reviewCoverage)
            ? (float) $reviewCoverage
            : 0.0;

        return new self(
            isSparse: (bool) ($payload['is_sparse'] ?? false),
            totalRuns: (int) ($payload['total_runs'] ?? 0),
            activeDays: (int) ($payload['active_days'] ?? 0),
            periodDays: (int) ($payload['period_days'] ?? 0),
            reviewCoverage: $normalizedReviewCoverage,
            notes: $normalizedNotes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_sparse' => $this->isSparse,
            'total_runs' => $this->totalRuns,
            'active_days' => $this->activeDays,
            'period_days' => $this->periodDays,
            'review_coverage' => $this->reviewCoverage,
            'notes' => $this->notes,
        ];
    }
}
